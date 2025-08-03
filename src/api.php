<?php
session_start();

// Load configuration
$config = require dirname(__DIR__) . '/config/database.php';

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $config['error_log']);

// Custom error handler for better debugging
function apiErrorHandler($errno, $errstr, $errfile, $errline) {
    $error = [
        'type' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'time' => date('Y-m-d H:i:s'),
        'request' => $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']
    ];
    global $config;
    error_log(json_encode($error) . PHP_EOL, 3, $config['api_error_log']);
    
    // Return JSON error response
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'details' => $errstr]);
    exit;
}
set_error_handler('apiErrorHandler');

// Performance: Enable output compression
if (!ob_get_level()) {
    ob_start('ob_gzhandler');
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Cache headers for API responses
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Include database helper
require_once __DIR__ . '/db-helper.php';

// Database configuration from config file
$db_path = $config['database_path'];
$backup_dir = $config['backup_dir'];

// Ensure backup directory exists
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Get database instance
$dbHelper = DatabaseHelper::getInstance();
$db = $dbHelper->getDb();

// Router
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

// Helper functions
function generateToken() {
    return bin2hex(random_bytes(32));
}

function generateNoteId() {
    // Generate a unique ID using timestamp and random bytes
    // Format: note_timestamp_randomhex
    $timestamp = microtime(true) * 10000; // microseconds
    $random = bin2hex(random_bytes(8));
    return 'note_' . intval($timestamp) . '_' . $random;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function authenticateRequest($dbHelper) {
    // Check for session token in HTTP-only cookie
    if (!isset($_SESSION['auth_token'])) {
        return null;
    }
    
    $token = $_SESSION['auth_token'];
    
    $stmt = $dbHelper->prepare('
        SELECT user_id 
        FROM sessions 
        WHERE token = ? AND expires_at > datetime("now")
    ');
    $stmt->bindValue(1, $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    $session = $result->fetchArray(SQLITE3_ASSOC);
    
    return $session ? $session['user_id'] : null;
}

function backupDatabase($db_path, $backup_dir) {
    $backup_file = $backup_dir . '/zornell_' . date('Y-m-d_H-i-s') . '.db';
    copy($db_path, $backup_file);
    
    // Keep only last 30 backups
    $backups = glob($backup_dir . '/zornell_*.db');
    rsort($backups);
    foreach (array_slice($backups, 30) as $old_backup) {
        unlink($old_backup);
    }
}

// Routes
switch ($path) {
    case 'register':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (!validateEmail($email)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            exit;
        }
        
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 8 characters']);
            exit;
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if email already exists
        $checkStmt = $dbHelper->prepare('SELECT id FROM users WHERE email = ?');
        $checkStmt->bindValue(1, $email, SQLITE3_TEXT);
        $result = $checkStmt->execute();
        
        if ($result->fetchArray()) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
            exit;
        }
        
        // Insert new user
        $stmt = $dbHelper->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $stmt->bindValue(2, $hash, SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed']);
        }
        break;
        
    case 'login':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        $stmt = $dbHelper->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Create session
            $token = generateToken();
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $dbHelper->prepare('INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, ?)');
            $stmt->bindValue(1, $token, SQLITE3_TEXT);
            $stmt->bindValue(2, $user['id'], SQLITE3_INTEGER);
            $stmt->bindValue(3, $expires, SQLITE3_TEXT);
            $stmt->execute();
            
            // Update last login
            $stmt = $dbHelper->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->bindValue(1, $user['id'], SQLITE3_INTEGER);
            $stmt->execute();
            
            // Set PHP session with HTTP-only cookie
            $_SESSION['auth_token'] = $token;
            $_SESSION['user_id'] = $user['id'];
            
            // Ensure session cookie is HTTP-only and secure
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires' => time() + 86400 * 30,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            echo json_encode([
                'success' => true,
                'user_id' => $user['id']
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
        break;
        
    case 'logout':
        // Don't check authentication for logout - just clear any existing session
        if (isset($_SESSION['auth_token'])) {
            $token = $_SESSION['auth_token'];
            
            $stmt = $dbHelper->prepare('DELETE FROM sessions WHERE token = ?');
            $stmt->bindValue(1, $token, SQLITE3_TEXT);
            $stmt->execute();
        }
        
        // Clear session
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        
        echo json_encode(['success' => true]);
        break;
        
    case 'notes':
        $user_id = authenticateRequest($dbHelper);
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        if ($method === 'GET') {
            // Get all notes for user
            $stmt = $dbHelper->prepare('
                SELECT note_id as id, title, content, type, urgent, date
                FROM notes 
                WHERE user_id = ?
                ORDER BY created_at ASC
            ');
            $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            $notes = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $row['urgent'] = (bool)$row['urgent'];
                $notes[] = $row;
            }
            
            echo json_encode($notes);
            
        } elseif ($method === 'POST') {
            // Sync notes - replace all
            $notes = json_decode(file_get_contents('php://input'), true);
            
            if (!is_array($notes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid data format']);
                exit;
            }
            
            // Backup before major operation
            backupDatabase($db_path, $backup_dir);
            
            // Start transaction
            $db->exec('BEGIN TRANSACTION');
            
            try {
                // Delete existing notes for user
                $stmt = $dbHelper->prepare('DELETE FROM notes WHERE user_id = ?');
                $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
                $stmt->execute();
                
                // Insert new notes
                $stmt = $dbHelper->prepare('
                    INSERT INTO notes (note_id, user_id, title, content, type, urgent, date)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                
                foreach ($notes as $note) {
                    $stmt->bindValue(1, $note['id'] ?? generateNoteId(), SQLITE3_TEXT);
                    $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
                    $stmt->bindValue(3, $note['title'] ?? '', SQLITE3_TEXT);
                    $stmt->bindValue(4, $note['content'] ?? '', SQLITE3_TEXT);
                    $stmt->bindValue(5, $note['type'] ?? 'personal', SQLITE3_TEXT);
                    $stmt->bindValue(6, $note['urgent'] ? 1 : 0, SQLITE3_INTEGER);
                    $stmt->bindValue(7, $note['date'] ?? date('Y-m-d'), SQLITE3_TEXT);
                    $stmt->execute();
                }
                
                $db->exec('COMMIT');
                echo json_encode(['success' => true, 'count' => count($notes)]);
                
            } catch (Exception $e) {
                $db->exec('ROLLBACK');
                http_response_code(500);
                echo json_encode(['error' => 'Failed to sync notes']);
            }
        } elseif ($method === 'DELETE') {
            // Delete a specific note
            $note_id = $_GET['id'] ?? '';
            
            if (!$note_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Note ID required']);
                exit;
            }
            
            $stmt = $dbHelper->prepare('DELETE FROM notes WHERE note_id = ? AND user_id = ?');
            $stmt->bindValue(1, $note_id, SQLITE3_TEXT);
            $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($db->changes() > 0) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Note not found']);
            }
        }
        break;
        
    case 'create_note':
        $user_id = authenticateRequest($dbHelper);
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Generate unique note ID
        $note_id = generateNoteId();
        
        // Insert new note
        $stmt = $dbHelper->prepare('
            INSERT INTO notes (note_id, user_id, title, content, type, urgent, date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->bindValue(1, $note_id, SQLITE3_TEXT);
        $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(3, $data['title'] ?? 'New Note', SQLITE3_TEXT);
        $stmt->bindValue(4, $data['content'] ?? 'Start typing...', SQLITE3_TEXT);
        $stmt->bindValue(5, $data['type'] ?? 'personal', SQLITE3_TEXT);
        $stmt->bindValue(6, isset($data['urgent']) ? ($data['urgent'] ? 1 : 0) : 0, SQLITE3_INTEGER);
        $stmt->bindValue(7, $data['date'] ?? date('n/j/Y'), SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            echo json_encode([
                'id' => $note_id,
                'title' => $data['title'] ?? 'New Note',
                'content' => $data['content'] ?? 'Start typing...',
                'type' => $data['type'] ?? 'personal',
                'urgent' => isset($data['urgent']) ? (bool)$data['urgent'] : false,
                'date' => $data['date'] ?? date('n/j/Y')
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create note']);
        }
        break;
        
    case 'note':
        $user_id = authenticateRequest($dbHelper);
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        if ($method === 'PUT') {
            try {
                // Update a specific note
                $note_id = $_GET['id'] ?? '';
                
                error_log("PUT request for note: $note_id by user: $user_id");
                
                if (!$note_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Note ID required']);
                    exit;
                }
                
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                
                error_log("PUT data received: " . $input);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
                    exit;
                }
                
                // Update note
                $stmt = $dbHelper->prepare('
                    UPDATE notes 
                    SET title = ?, content = ?, type = ?, urgent = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE note_id = ? AND user_id = ?
                ');
                
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement: " . $db->lastErrorMsg());
                }
                
                $stmt->bindValue(1, $data['title'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(2, $data['content'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(3, $data['type'] ?? 'personal', SQLITE3_TEXT);
                $stmt->bindValue(4, isset($data['urgent']) ? ($data['urgent'] ? 1 : 0) : 0, SQLITE3_INTEGER);
                $stmt->bindValue(5, $note_id, SQLITE3_TEXT);
                $stmt->bindValue(6, $user_id, SQLITE3_INTEGER);
                
                $result = $stmt->execute();
                
                if (!$result) {
                    throw new Exception("Failed to execute update: " . $db->lastErrorMsg());
                }
                
                if ($db->changes() > 0) {
                    echo json_encode(['success' => true]);
                    error_log("Note $note_id updated successfully");
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Note not found']);
                    error_log("Note $note_id not found for user $user_id");
                }
            } catch (Exception $e) {
                error_log("PUT error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update note', 'details' => $e->getMessage()]);
            }
        }
        break;
        
    case 'health':
        // Health check endpoint
        echo json_encode([
            'status' => 'ok',
            'time' => date('Y-m-d H:i:s'),
            'db_size' => filesize($db_path),
            'backup_count' => count(glob($backup_dir . '/zornell_*.db'))
        ]);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}
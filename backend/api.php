<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Database configuration
$db_path = __DIR__ . '/data/zornell.db';
$backup_dir = __DIR__ . '/data/backups';

// Ensure directories exist
if (!file_exists(dirname($db_path))) {
    mkdir(dirname($db_path), 0755, true);
}
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Initialize database
$db = new SQLite3($db_path);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec('PRAGMA journal_mode = WAL'); // Better concurrency

// Create tables if not exist
$schema = file_get_contents(__DIR__ . '/schema.sql');
$db->exec($schema);

// Router
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

// Helper functions
function generateToken() {
    return bin2hex(random_bytes(32));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function authenticateRequest($db) {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (!$token) {
        return null;
    }
    
    $stmt = $db->prepare('
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
        $checkStmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $checkStmt->bindValue(1, $email, SQLITE3_TEXT);
        $result = $checkStmt->execute();
        
        if ($result->fetchArray()) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
            exit;
        }
        
        // Insert new user
        $stmt = $db->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
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
        
        $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Create session
            $token = generateToken();
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $db->prepare('INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, ?)');
            $stmt->bindValue(1, $token, SQLITE3_TEXT);
            $stmt->bindValue(2, $user['id'], SQLITE3_INTEGER);
            $stmt->bindValue(3, $expires, SQLITE3_TEXT);
            $stmt->execute();
            
            // Update last login
            $stmt = $db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->bindValue(1, $user['id'], SQLITE3_INTEGER);
            $stmt->execute();
            
            echo json_encode([
                'token' => $token,
                'user_id' => $user['id'],
                'email' => $email
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
        break;
        
    case 'logout':
        $user_id = authenticateRequest($db);
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        $stmt = $db->prepare('DELETE FROM sessions WHERE token = ?');
        $stmt->bindValue(1, $token, SQLITE3_TEXT);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        break;
        
    case 'notes':
        $user_id = authenticateRequest($db);
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        if ($method === 'GET') {
            // Get all notes for user
            $stmt = $db->prepare('
                SELECT id, title, content, type, urgent, date
                FROM notes 
                WHERE user_id = ?
                ORDER BY updated_at DESC
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
                $stmt = $db->prepare('DELETE FROM notes WHERE user_id = ?');
                $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
                $stmt->execute();
                
                // Insert new notes
                $stmt = $db->prepare('
                    INSERT INTO notes (id, user_id, title, content, type, urgent, date)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                
                foreach ($notes as $note) {
                    $stmt->bindValue(1, $note['id'] ?? uniqid(), SQLITE3_TEXT);
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
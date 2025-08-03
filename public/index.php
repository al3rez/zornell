<?php
session_start();

// Load configuration
$config = require dirname(__DIR__) . '/config/database.php';

// Handle logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['auth_token'])) {
        try {
            $db = new SQLite3($config['database_path']);
            $stmt = $db->prepare('DELETE FROM sessions WHERE token = ?');
            $stmt->bindValue(1, $_SESSION['auth_token'], SQLITE3_TEXT);
            $stmt->execute();
            $db->close();
        } catch (Exception $e) {}
    }
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        setcookie(session_name(), '', time() - 3600, $params["path"]);
        setcookie(session_name(), '', time() - 3600, '/');
        setcookie('PHPSESSID', '', time() - 3600, '/');
    }
    session_destroy();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Location: /');
    exit();
}

header('Cache-Control: private, max-age=300');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');

// Check authentication
$isAuthenticated = false;
$userEmail = null;
$authToken = null;
$userId = null;
$userNotes = [];

if (isset($_SESSION['auth_token'])) {
    $db = new SQLite3($config['database_path']);
    $stmt = $db->prepare('SELECT u.email, s.token, s.user_id FROM sessions s JOIN users u ON s.user_id = u.id WHERE s.token = ? AND s.expires_at > datetime("now")');
    $stmt->bindValue(1, $_SESSION['auth_token'], SQLITE3_TEXT);
    $result = $stmt->execute();
    $session = $result->fetchArray(SQLITE3_ASSOC);

    if ($session) {
        $isAuthenticated = true;
        $userEmail = $session['email'];
        $authToken = $session['token'];
        $userId = $session['user_id'];
        $notesStmt = $db->prepare('SELECT note_id as id, title, content, type, urgent, date FROM notes WHERE user_id = ? ORDER BY created_at ASC');
        $notesStmt->bindValue(1, $userId, SQLITE3_INTEGER);
        $notesResult = $notesStmt->execute();
        while ($row = $notesResult->fetchArray(SQLITE3_ASSOC)) {
            $row['urgent'] = (bool) $row['urgent'];
            $userNotes[] = $row;
        }
    } else {
        unset($_SESSION['auth_token']);
    }
    $db->close();
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZORNELL - Fast Notes</title>
    <meta name="description" content="Fast, efficient note-taking app with multi-select and keyboard shortcuts">
    <script>
        const AUTH_API_URL = '/backend/api.php';
        const IS_AUTHENTICATED = <?php echo $isAuthenticated ? 'true' : 'false'; ?>;
        const USER_EMAIL = <?php echo $userEmail ? "'$userEmail'" : 'null'; ?>;

        // JavaScript equivalent of PHP's htmlspecialchars
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        class ZornellAuth {
            constructor() {
                // No longer storing token in JavaScript
            }
            isAuthenticated() {
                return IS_AUTHENTICATED;
            }
            async register(email, password) {
                const response = await fetch(`${AUTH_API_URL}?action=register`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ email, password })
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Registration failed');
                return data;
            }
            async login(email, password) {
                const response = await fetch(`${AUTH_API_URL}?action=login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ email, password })
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Login failed');
                window.location.reload();
                return data;
            }
            async logout() {
                await fetch('/backend/api.php?action=logout', {
                    method: 'POST',
                    credentials: 'same-origin'
                }).catch(() => {});
                // Force reload to clear all state
                window.location.reload(true);
            }
            async syncNotes(notes) {
                if (!IS_AUTHENTICATED) throw new Error('Not authenticated');
                const response = await fetch(`${AUTH_API_URL}?action=notes`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(notes)
                });
                if (response.status === 401) {
                    await this.logout();
                    throw new Error('Session expired. Please login again.');
                }
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Sync failed');
                return data;
            }
            async fetchNotes() {
                if (!IS_AUTHENTICATED) throw new Error('Not authenticated');
                const response = await fetch(`${AUTH_API_URL}?action=notes`, {
                    credentials: 'same-origin'
                });
                if (response.status === 401) {
                    await this.logout();
                    throw new Error('Session expired. Please login again.');
                }
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Failed to fetch notes');
                return data;
            }
            async createNote(noteData) {
                if (!IS_AUTHENTICATED) throw new Error('Not authenticated');
                const response = await fetch(`${AUTH_API_URL}?action=create_note`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(noteData)
                });
                if (response.status === 401) {
                    await this.logout();
                    throw new Error('Session expired. Please login again.');
                }
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Failed to create note');
                return data;
            }
        }

        function createAuthForm() {
            const container = document.createElement('div');
            container.className = 'auth-container';
            
            const authForm = document.createElement('div');
            authForm.className = 'auth-form';
            
            const title = document.createElement('h2');
            title.textContent = 'ZORNELL';
            
            const subtitle = document.createElement('p');
            subtitle.className = 'auth-subtitle';
            subtitle.textContent = 'Lightning-fast notes that sync everywhere';
            
            const form = document.createElement('form');
            form.id = 'authForm';
            
            const emailInput = document.createElement('input');
            emailInput.type = 'email';
            emailInput.id = 'authEmail';
            emailInput.placeholder = 'Email';
            emailInput.required = true;
            emailInput.autocomplete = 'off';
            
            const passwordInput = document.createElement('input');
            passwordInput.type = 'password';
            passwordInput.id = 'authPassword';
            passwordInput.placeholder = 'Password';
            passwordInput.required = true;
            passwordInput.minLength = 8;
            passwordInput.autocomplete = 'off';
            
            const buttonDiv = document.createElement('div');
            buttonDiv.className = 'auth-buttons';
            
            const loginBtn = document.createElement('button');
            loginBtn.type = 'submit';
            loginBtn.id = 'loginBtn';
            loginBtn.textContent = 'Login';
            
            const registerBtn = document.createElement('button');
            registerBtn.type = 'button';
            registerBtn.id = 'registerBtn';
            registerBtn.textContent = 'Register';
            
            buttonDiv.appendChild(loginBtn);
            buttonDiv.appendChild(registerBtn);
            
            const errorDiv = document.createElement('div');
            errorDiv.id = 'authError';
            errorDiv.className = 'auth-error';
            
            form.appendChild(emailInput);
            form.appendChild(passwordInput);
            form.appendChild(buttonDiv);
            form.appendChild(errorDiv);
            
            const footer = document.createElement('div');
            footer.className = 'auth-footer';
            
            const featureList = document.createElement('div');
            featureList.className = 'feature-list';
            
            const feature1 = document.createElement('p');
            feature1.textContent = '✓ Capture thoughts in seconds, not minutes';
            
            const feature2 = document.createElement('p');
            feature2.textContent = '✓ Works offline, syncs when connected';
            
            const feature3 = document.createElement('p');
            feature3.textContent = '✓ Export to TXT, JSON, or Markdown';
            
            featureList.appendChild(feature1);
            featureList.appendChild(feature2);
            featureList.appendChild(feature3);
            
            footer.appendChild(featureList);
            
            authForm.appendChild(title);
            authForm.appendChild(subtitle);
            authForm.appendChild(form);
            authForm.appendChild(footer);
            
            container.appendChild(authForm);
            
            return container;
        }

        window.ZornellAuth = ZornellAuth;
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background-color: #000;
            color: #0ff;
            font-family: 'TX-02', 'Liberation Mono', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.4;
            padding: 10px;
            min-height: 100vh;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ff0;
            gap: 5px;
        }
        h1 {
            color: #ff0;
            font-size: 1.2em;
            font-weight: 700;
        }
        .controls {
            display: flex;
            gap: 5px;
            align-items: center;
            margin-left: auto;
        }
        .search-delete-container {
            position: relative;
            width: 150px;
            height: auto;
            display: flex;
            align-items: center;
        }
        .search-box,
        .delete-selected-btn {
            width: 100%;
            padding: 6px 12px;
            font-size: 12px;
            font-family: inherit;
            border-radius: 2px;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .search-box {
            background-color: #111;
            border: 1px solid #0ff;
            color: #0ff;
            display: block;
        }
        .search-box:focus {
            outline: none;
            border-color: #ff0;
        }
        .delete-selected-btn {
            background-color: transparent;
            border: 1px solid #f00;
            color: #f00;
            cursor: pointer;
            font-weight: 600;
            text-transform: uppercase;
            display: none;
        }
        .delete-selected-btn:hover {
            background-color: #f00;
            color: #fff;
        }
        body.has-selection .search-box {
            display: none;
        }
        body.has-selection .delete-selected-btn {
            display: block;
        }
        .filter-btn {
            padding: 6px 12px;
            background-color: transparent;
            border: 1px solid #0ff;
            color: #0ff;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            border-radius: 2px;
            transition: all 0.1s ease;
        }
        .filter-btn:hover {
            transform: translateY(-1px);
        }
        .filter-btn.active {
            background-color: #0ff;
            color: #000;
        }
        .notes-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 8px;
            contain: layout;
            will-change: contents;
        }
        .note-card {
            border: 1px solid #0ff;
            padding: 12px;
            background-color: rgba(0, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            min-height: 150px;
            border-radius: 2px;
            transition: transform 0.1s ease;
            will-change: transform;
            transform: translateZ(0);
            backface-visibility: hidden;
            contain: layout style paint;
            cursor: pointer;
            user-select: none;
            perspective: 1000px;
        }
        @keyframes digitalMaterialize {
            0% {
                opacity: 0;
                transform: translateY(20px) scale(0.8) rotateX(90deg);
                filter: blur(10px);
                box-shadow: 0 0 0 rgba(0, 255, 255, 0);
            }
            50% {
                opacity: 0.5;
                filter: blur(5px);
                box-shadow: 0 0 30px rgba(0, 255, 255, 0.8);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1) rotateX(0);
                filter: blur(0);
                box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
            }
        }
        .note-card.new-note {
            animation: digitalMaterialize 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-style: preserve-3d;
        }
        @keyframes digitalDisintegrate {
            0% {
                opacity: 1;
                transform: scale(1) translateY(0);
                filter: saturate(1) hue-rotate(0deg);
            }
            30% {
                opacity: 0.8;
                transform: scale(1.05) translateY(-2px);
                filter: saturate(2) hue-rotate(180deg);
                box-shadow: 0 0 40px rgba(255, 0, 0, 0.8);
            }
            60% {
                opacity: 0.4;
                transform: scale(0.95) translateY(5px);
                filter: saturate(0) hue-rotate(360deg);
            }
            100% {
                opacity: 0;
                transform: scale(0.8) translateY(20px);
                filter: blur(10px);
                box-shadow: 0 0 0 rgba(255, 0, 0, 0);
            }
        }
        @keyframes staticNoise {
            0%, 100% { opacity: 0; }
            50% { opacity: 0.8; }
        }
        .note-card.deleting {
            animation: digitalDisintegrate 0.3s ease-in forwards;
            pointer-events: none;
            position: relative;
            overflow: hidden;
        }
        .note-card.deleting::after {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(0deg, rgba(255, 0, 0, 0.1) 0px, transparent 1px, transparent 2px, rgba(255, 0, 0, 0.1) 3px);
            animation: staticNoise 0.3s steps(1);
            mix-blend-mode: multiply;
        }
        .note-card:hover {
            transform: translateY(-2px);
        }
        .note-card.personal.selected {
            border-color: #0ff;
            background-color: rgba(0, 255, 255, 0.15);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
        }
        .note-card.work.selected {
            border-color: #f0f;
            background-color: rgba(255, 0, 255, 0.15);
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.3);
        }
        .note-card.urgent.selected {
            border-color: #f00;
            background-color: rgba(255, 0, 0, 0.15);
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);
        }
        .note-card.work {
            border-color: #f0f;
            background-color: rgba(255, 0, 255, 0.05);
        }
        .note-card.urgent {
            border-color: #f00;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.5);
        }
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .note-title {
            color: #ff0;
            font-size: 1em;
            font-weight: 600;
            flex: 1;
            margin-right: 8px;
        }
        .note-meta {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        .tag {
            padding: 2px 6px;
            border: 1px solid #0ff;
            font-size: 10px;
            font-weight: 600;
            border-radius: 2px;
        }
        .tag.work {
            border-color: #f0f;
            color: #f0f;
        }
        .tag.urgent {
            border-color: #f00;
            color: #f00;
            background-color: rgba(255, 0, 0, 0.1);
        }
        .tag.personal {
            border-color: #0ff;
            color: #0ff;
        }
        .note-content {
            flex: 1;
            line-height: 1.4;
            margin-bottom: 8px;
            font-size: 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 120px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            will-change: scroll-position;
        }
        .note-content.loading {
            color: #888;
            font-style: italic;
        }
        .note-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #333;
            padding-top: 6px;
        }
        .note-date {
            color: #888;
            font-size: 10px;
        }
        .note-actions {
            display: flex;
            gap: 5px;
        }
        .action-btn {
            padding: 3px 6px;
            background-color: transparent;
            border: 1px solid #0ff;
            color: #0ff;
            cursor: pointer;
            font-size: 10px;
            font-weight: 600;
            font-family: inherit;
            border-radius: 2px;
            transition: all 0.1s ease;
        }
        .action-btn:hover {
            background-color: #0ff;
            color: #000;
        }
        [contenteditable] {
            outline: none;
        }
        [contenteditable]:focus {
            background-color: rgba(0, 255, 255, 0.1);
            padding: 4px;
            border-radius: 4px;
        }
        .add-note-placeholder {
            border: 1px dashed #ff0;
            background-color: rgba(255, 255, 0, 0.02);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .add-note-placeholder:hover {
            background-color: rgba(255, 255, 0, 0.1);
            border-color: #ff0;
            transform: translateY(-2px);
        }
        .placeholder-content {
            text-align: center;
            color: #ff0;
        }
        .plus-icon {
            display: block;
            font-size: 2em;
            font-weight: 300;
            margin-bottom: 5px;
        }
        .placeholder-text {
            display: block;
            font-size: 0.9em;
            opacity: 0.8;
        }
        .add-note {
            position: fixed;
            bottom: 15px;
            right: 15px;
            width: 40px;
            height: 40px;
            background-color: #ff0;
            color: #000;
            border: none;
            border-radius: 50%;
            font-size: 1.5em;
            font-weight: 300;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(255, 255, 0, 0.4);
            transition: all 0.1s ease;
            display: none;
        }
        .add-note:hover {
            transform: scale(1.1);
        }
        .add-note:active {
            transform: scale(0.95);
        }
        @media (max-width: 768px) {
            .add-note.mobile-only {
                display: block;
            }
            .add-note-placeholder {
                display: none;
            }
            .header {
                flex-wrap: wrap;
            }
            .controls {
                order: 2;
                width: 100%;
                margin-top: 10px;
            }
            .user-section {
                order: 1;
            }
        }
        .export-btn {
            padding: 6px 12px;
            background-color: transparent;
            border: 1px solid #0ff;
            color: #0ff;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            font-family: inherit;
            border-radius: 2px;
            transition: all 0.1s ease;
        }
        .export-btn:hover {
            transform: translateY(-1px);
        }
        .export-btn.active {
            background-color: #0ff;
            color: #000;
        }
        .export-menu {
            position: absolute;
            top: calc(100% + 5px);
            right: 0;
            background-color: #111;
            border: 1px solid #ff0;
            border-radius: 2px;
            overflow: hidden;
            z-index: 1000;
            min-width: 150px;
        }
        .export-option {
            display: block;
            width: 100%;
            padding: 8px 16px;
            background-color: transparent;
            border: none;
            border-bottom: 1px solid #333;
            color: #0ff;
            cursor: pointer;
            font-size: 12px;
            font-family: inherit;
            text-align: left;
        }
        .export-option:last-child {
            border-bottom: none;
        }
        .export-option:hover {
            background-color: rgba(0, 255, 255, 0.1);
        }
        .hidden {
            display: none !important;
        }
        .auth-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            background: rgba(0, 0, 0, 0.05);
        }
        .auth-form {
            background: #111;
            border: 1px solid #0ff;
            padding: 40px;
            border-radius: 2px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3);
            width: 100%;
            max-width: 400px;
        }
        .auth-form h2 {
            color: #ff0;
            margin: 0 0 8px 0;
            font-size: 1.8em;
            text-align: center;
            font-weight: 700;
        }
        .auth-subtitle {
            color: #0ff;
            text-align: center;
            margin: 0 0 32px 0;
            font-size: 0.9em;
        }
        .auth-form input {
            width: 100%;
            padding: 12px 16px;
            margin-bottom: 16px;
            border: 1px solid #0ff;
            border-radius: 2px;
            font-size: 14px;
            background: #000;
            color: #0ff;
            font-family: inherit;
        }
        .auth-form input:focus {
            outline: none;
            border-color: #ff0;
            box-shadow: 0 0 10px rgba(255, 255, 0, 0.3);
        }
        .auth-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .auth-buttons button {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 2px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.1s;
            font-family: inherit;
            text-transform: uppercase;
        }
        #loginBtn {
            background: #0ff;
            color: #000;
            border: 1px solid #0ff;
        }
        #loginBtn:hover {
            background: #000;
            color: #0ff;
        }
        #registerBtn {
            background: transparent;
            color: #ff0;
            border: 1px solid #ff0;
        }
        #registerBtn:hover {
            background: #ff0;
            color: #000;
        }
        .auth-error {
            color: #f00;
            text-align: center;
            margin-top: 16px;
            font-size: 12px;
        }
        .auth-footer {
            margin-top: 32px;
            text-align: center;
        }
        .auth-footer p {
            color: #888;
            font-size: 11px;
        }
        .feature-list {
            text-align: left;
        }
        .feature-list p {
            color: #0ff;
            font-size: 12px;
            margin: 6px 0;
            opacity: 0.8;
        }
        .user-section {
            display: flex;
            align-items: center;
            gap: 5px;
            position: relative;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .logout-btn,
        a.logout-btn {
            padding: 6px 12px;
            background: transparent;
            border: 1px solid #f00;
            border-radius: 2px;
            color: #f00;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.1s;
            font-family: inherit;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        .logout-btn:hover,
        a.logout-btn:hover {
            background: #f00;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container" <?php echo !$isAuthenticated ? ' style="display: none;"' : ''; ?>>
        <div class="header">
            <h1>ZORNELL</h1>
            <div class="controls">
                <div class="search-delete-container">
                    <input type="text" class="search-box" placeholder="Search..." id="searchBox" spellcheck="true" autocomplete="on" autocorrect="on" aria-label="Search notes">
                    <button class="delete-selected-btn" id="deleteSelectedBtn" onclick="deleteSelectedNotes()">DELETE <span id="deleteCount">0</span></button>
                </div>
                <button class="filter-btn active" onclick="setFilter('all', this)">ALL</button>
                <button class="filter-btn" onclick="setFilter('work', this)">WORK</button>
                <button class="filter-btn" onclick="setFilter('personal', this)">PERSONAL</button>
                <button class="filter-btn" onclick="setFilter('urgent', this)">URGENT</button>
            </div>
            <div class="user-section">
                <button class="export-btn" onclick="showExportMenu()" title="More options">⋮</button>
                <?php if ($isAuthenticated): ?>
                    <div class="user-info" id="userInfo">
                        <a href="#" class="logout-btn" onclick="auth.logout(); return false;">LOGOUT</a>
                    </div>
                <?php else: ?>
                    <div class="user-info" id="userInfo" style="display: none;">
                        <a href="#" class="logout-btn" onclick="auth.logout(); return false;">LOGOUT</a>
                    </div>
                <?php endif; ?>
                <div class="export-menu" id="exportMenu" style="display: none;">
                    <button class="export-option" onclick="exportAsJSON()">Export as JSON</button>
                    <button class="export-option" onclick="exportAsText()">Export as TXT</button>
                    <button class="export-option" onclick="exportAsMarkdown()">Export as MD</button>
                    <button class="export-option" onclick="window.print()">Print / PDF</button>
                </div>
            </div>
        </div>
        <div class="notes-container" id="notesContainer">
            <?php if ($isAuthenticated && count($userNotes) > 0): ?>
                <?php foreach ($userNotes as $note): ?>
                    <div class="note-card <?php echo h($note['type']); ?><?php echo $note['urgent'] ? ' urgent' : ''; ?>" data-id="<?php echo h($note['id']); ?>">
                        <div class="note-header">
                            <div class="note-title" contenteditable="true" spellcheck="true" autocomplete="on" autocorrect="on" autocapitalize="on" onfocus="clearDefaultText(this, 'New Note')" onblur="updateNote('<?php echo h($note['id']); ?>', 'title', this.textContent)" onmousedown="handleContentClick(event, '<?php echo h($note['id']); ?>')" onclick="event.stopPropagation()"><?php echo h($note['title']); ?></div>
                            <div class="note-meta">
                                <span class="tag <?php echo h($note['type']); ?>"><?php echo strtoupper(h($note['type'])); ?></span>
                                <?php if ($note['urgent']): ?>
                                    <span class="tag urgent">URGENT</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="note-content" contenteditable="true" spellcheck="true" autocomplete="on" autocorrect="on" autocapitalize="sentences" onfocus="clearDefaultText(this, 'Start typing...')" onblur="updateNote('<?php echo h($note['id']); ?>', 'content', this.innerText)" onmousedown="handleContentClick(event, '<?php echo h($note['id']); ?>')" onclick="event.stopPropagation()"><?php echo h($note['content']); ?></div>
                        <div class="note-footer">
                            <span class="note-date"><?php echo h($note['date']); ?></span>
                            <div class="note-actions">
                                <button class="action-btn" onclick="toggleType(event, '<?php echo h($note['id']); ?>')"><?php echo $note['type'] === 'work' ? 'PERSONAL' : 'WORK'; ?></button>
                                <button class="action-btn" onclick="toggleUrgent(event, '<?php echo h($note['id']); ?>')">URGENT</button>
                                <button class="action-btn" onclick="event.stopPropagation(); deleteNote('<?php echo h($note['id']); ?>')">DELETE</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="note-card add-note-placeholder" onclick="addNewNote()">
                <div class="placeholder-content">
                    <span class="plus-icon">+</span>
                    <span class="placeholder-text">New Note</span>
                </div>
            </div>
        </div>
        <button class="add-note mobile-only" onclick="addNewNote()">+</button>
    </div>
    <script>
        const notesMap = new Map();
        let currentFilter = 'all';
        let noteIdCounter = 1;
        const selectedNotes = new Set();
        let lastClickedNote = null;
        let renderScheduled = false;
        let auth = null;
        let syncInterval = null;

        async function addNewNote() {
            let note;
            if (IS_AUTHENTICATED) {
                try {
                    note = await auth.createNote({
                        title: 'New Note',
                        content: 'Start typing...',
                        type: 'personal',
                        urgent: false,
                        date: new Date().toLocaleDateString()
                    });
                    note.isNew = true;
                } catch (error) {
                    console.error('Error creating note:', error);
                    note = {
                        id: 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                        title: 'New Note',
                        content: 'Start typing...',
                        type: 'personal',
                        urgent: false,
                        date: new Date().toLocaleDateString(),
                        isNew: true,
                        isTemp: true
                    };
                }
            } else {
                note = {
                    id: noteIdCounter++,
                    title: 'New Note',
                    content: 'Start typing...',
                    type: 'personal',
                    urgent: false,
                    date: new Date().toLocaleDateString(),
                    isNew: true
                };
            }
            notesMap.set(note.id, note);
            const container = document.getElementById('notesContainer');
            const placeholder = container.querySelector('.add-note-placeholder');
            const noteElement = createNoteElement(note);
            container.insertBefore(noteElement, placeholder);
            noteElement.offsetHeight;
            noteElement.classList.add('new-note');
            setTimeout(() => {
                noteElement.classList.remove('new-note');
                delete note.isNew;
            }, 400);
            requestAnimationFrame(() => {
                const titleElement = noteElement.querySelector('.note-title');
                if (titleElement) {
                    titleElement.focus();
                    const range = document.createRange();
                    range.selectNodeContents(titleElement);
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            });
        }

        function setFilter(filter, btn) {
            currentFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderNotes();
        }

        function filterNotes() {
            renderNotes();
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function renderNotes() {
            if (renderScheduled) return;
            renderScheduled = true;
            requestAnimationFrame(() => {
                renderScheduled = false;
                const container = document.getElementById('notesContainer');
                const searchTerm = document.getElementById('searchBox').value.toLowerCase();
                const notesArray = Array.from(notesMap.values());
                const filteredNotes = notesArray.filter(note => {
                    let matchesFilter = currentFilter === 'all';
                    if (currentFilter === 'work') matchesFilter = note.type === 'work';
                    if (currentFilter === 'personal') matchesFilter = note.type === 'personal';
                    if (currentFilter === 'urgent') matchesFilter = note.urgent === true;
                    if (!matchesFilter) return false;
                    const matchesSearch = !searchTerm ||
                        note.title.toLowerCase().includes(searchTerm) ||
                        note.content.toLowerCase().includes(searchTerm);
                    return matchesSearch;
                });
                const fragment = document.createDocumentFragment();
                const existingElements = new Map();
                container.querySelectorAll('.note-card:not(.add-note-placeholder)').forEach(el => {
                    existingElements.set(el.dataset.id, el);
                });
                const placeholder = container.querySelector('.add-note-placeholder');
                if (placeholder) placeholder.remove();
                filteredNotes.forEach(note => {
                    let noteCard = existingElements.get(note.id);
                    if (noteCard) {
                        noteCard.className = `note-card ${note.type}${note.urgent ? ' urgent' : ''}${selectedNotes.has(note.id) ? ' selected' : ''}`;
                        existingElements.delete(note.id);
                    } else {
                        noteCard = createNoteElement(note);
                    }
                    fragment.appendChild(noteCard);
                });
                existingElements.forEach(el => el.remove());
                container.appendChild(fragment);
                const newPlaceholder = document.createElement('div');
                newPlaceholder.className = 'note-card add-note-placeholder';
                newPlaceholder.onclick = addNewNote;
                
                const placeholderContent = document.createElement('div');
                placeholderContent.className = 'placeholder-content';
                
                const plusIcon = document.createElement('span');
                plusIcon.className = 'plus-icon';
                plusIcon.textContent = '+';
                
                const placeholderText = document.createElement('span');
                placeholderText.className = 'placeholder-text';
                placeholderText.textContent = 'New Note';
                
                placeholderContent.appendChild(plusIcon);
                placeholderContent.appendChild(placeholderText);
                newPlaceholder.appendChild(placeholderContent);
                
                container.appendChild(newPlaceholder);
                attachNoteHandlers();
            });
        }

        function createNoteElement(note) {
            const div = document.createElement('div');
            div.className = `note-card ${note.type}${note.urgent ? ' urgent' : ''}${selectedNotes.has(note.id) ? ' selected' : ''}`;
            div.dataset.id = note.id;
            div.onclick = (e) => handleNoteClick(e, note.id);
            
            // Create note header
            const header = document.createElement('div');
            header.className = 'note-header';
            
            // Create title element with safe text content
            const title = document.createElement('div');
            title.className = 'note-title';
            title.contentEditable = true;
            title.spellcheck = true;
            title.autocomplete = 'on';
            title.autocorrect = 'on';
            title.autocapitalize = 'on';
            title.textContent = note.title; // Safe text insertion
            title.onfocus = () => clearDefaultText(title, 'New Note');
            title.onblur = () => updateNote(note.id, 'title', title.textContent);
            title.onmousedown = (e) => handleContentClick(e, note.id);
            title.onclick = (e) => e.stopPropagation();
            
            // Create meta container
            const meta = document.createElement('div');
            meta.className = 'note-meta';
            
            const typeTag = document.createElement('span');
            typeTag.className = `tag ${note.type}`;
            typeTag.textContent = note.type.toUpperCase();
            meta.appendChild(typeTag);
            
            if (note.urgent) {
                const urgentTag = document.createElement('span');
                urgentTag.className = 'tag urgent';
                urgentTag.textContent = 'URGENT';
                meta.appendChild(urgentTag);
            }
            
            header.appendChild(title);
            header.appendChild(meta);
            
            // Create content element with safe text content
            const content = document.createElement('div');
            content.className = 'note-content';
            content.contentEditable = true;
            content.spellcheck = true;
            content.autocomplete = 'on';
            content.autocorrect = 'on';
            content.autocapitalize = 'sentences';
            content.textContent = note.content; // Safe text insertion
            content.onfocus = () => clearDefaultText(content, 'Start typing...');
            content.onblur = () => updateNote(note.id, 'content', content.innerText);
            content.onmousedown = (e) => handleContentClick(e, note.id);
            content.onclick = (e) => e.stopPropagation();
            
            // Create footer
            const footer = document.createElement('div');
            footer.className = 'note-footer';
            
            const date = document.createElement('span');
            date.className = 'note-date';
            date.textContent = note.date;
            
            const actions = document.createElement('div');
            actions.className = 'note-actions';
            
            const typeBtn = document.createElement('button');
            typeBtn.className = 'action-btn';
            typeBtn.textContent = note.type === 'work' ? 'PERSONAL' : 'WORK';
            typeBtn.onclick = (e) => toggleType(e, note.id);
            
            const urgentBtn = document.createElement('button');
            urgentBtn.className = 'action-btn';
            urgentBtn.textContent = 'URGENT';
            urgentBtn.onclick = (e) => toggleUrgent(e, note.id);
            
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'action-btn';
            deleteBtn.textContent = 'DELETE';
            deleteBtn.onclick = (e) => { e.stopPropagation(); deleteNote(note.id); };
            
            actions.appendChild(typeBtn);
            actions.appendChild(urgentBtn);
            actions.appendChild(deleteBtn);
            
            footer.appendChild(date);
            footer.appendChild(actions);
            
            // Assemble the note card
            div.appendChild(header);
            div.appendChild(content);
            div.appendChild(footer);
            
            return div;
        }

        function clearDefaultText(element, defaultText) {
            if (element.textContent === defaultText) element.textContent = '';
        }

        const updateQueue = new Map();
        let updateTimer = null;

        async function updateNote(id, field, value) {
            const note = notesMap.get(id);
            if (note) {
                note[field] = value;
                if (IS_AUTHENTICATED) {
                    if (!updateQueue.has(id)) updateQueue.set(id, {});
                    updateQueue.get(id)[field] = value;
                    if (updateTimer) clearTimeout(updateTimer);
                    updateTimer = setTimeout(async () => {
                        for (const [noteId, fields] of updateQueue) {
                            const noteToUpdate = notesMap.get(noteId);
                            if (noteToUpdate) {
                                try {
                                    const response = await fetch(`${AUTH_API_URL}?action=note&id=${noteId}`, {
                                        method: 'PUT',
                                        headers: {
                                            'Content-Type': 'application/json'
                                        },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({
                                            title: noteToUpdate.title,
                                            content: noteToUpdate.content,
                                            type: noteToUpdate.type,
                                            urgent: noteToUpdate.urgent
                                        })
                                    });
                                    if (!response.ok) console.error('Failed to update note on server');
                                } catch (error) {
                                    console.error('Error updating note:', error);
                                }
                            }
                        }
                        updateQueue.clear();
                        updateTimer = null;
                    }, 500);
                }
            }
        }

        async function toggleType(event, id) {
            event.stopPropagation();
            const note = notesMap.get(id);
            if (note) {
                const oldType = note.type;
                note.type = note.type === 'work' ? 'personal' : 'work';
                const noteElement = document.querySelector(`[data-id="${id}"]`);
                if (noteElement) {
                    noteElement.classList.remove(oldType);
                    noteElement.classList.add(note.type);
                    const typeTag = noteElement.querySelector('.tag:not(.urgent)');
                    typeTag.textContent = note.type.toUpperCase();
                    typeTag.className = `tag ${note.type}`;
                    const actionBtns = noteElement.querySelectorAll('.action-btn');
                    if (actionBtns[0]) actionBtns[0].textContent = note.type === 'work' ? 'PERSONAL' : 'WORK';
                }
                if (IS_AUTHENTICATED) {
                    try {
                        await fetch(`${AUTH_API_URL}?action=note&id=${id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                title: note.title,
                                content: note.content,
                                type: note.type,
                                urgent: note.urgent
                            })
                        });
                    } catch (error) {
                        console.error('Error updating note type:', error);
                    }
                }
            }
        }

        async function toggleUrgent(event, id) {
            event.stopPropagation();
            const note = notesMap.get(id);
            if (note) {
                note.urgent = !note.urgent;
                const noteElement = document.querySelector(`[data-id="${id}"]`);
                if (noteElement) {
                    if (note.urgent) {
                        noteElement.classList.add('urgent');
                        const urgentTag = noteElement.querySelector('.tag.urgent');
                        if (!urgentTag) {
                            const metaDiv = noteElement.querySelector('.note-meta');
                            const newUrgentTag = document.createElement('span');
                            newUrgentTag.className = 'tag urgent';
                            newUrgentTag.textContent = 'URGENT';
                            metaDiv.appendChild(newUrgentTag);
                        }
                    } else {
                        noteElement.classList.remove('urgent');
                        const urgentTag = noteElement.querySelector('.tag.urgent');
                        if (urgentTag) urgentTag.remove();
                    }
                }
                if (IS_AUTHENTICATED) {
                    try {
                        await fetch(`${AUTH_API_URL}?action=note&id=${id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                title: note.title,
                                content: note.content,
                                type: note.type,
                                urgent: note.urgent
                            })
                        });
                    } catch (error) {
                        console.error('Error updating note urgency:', error);
                    }
                }
            }
        }

        function handleContentClick(event, noteId) {
            if ((event.ctrlKey || event.metaKey) && selectedNotes.size > 0) {
                event.preventDefault();
                event.stopPropagation();
                const noteCard = document.querySelector(`[data-id="${noteId}"]`);
                if (selectedNotes.has(noteId)) {
                    selectedNotes.delete(noteId);
                    noteCard.classList.remove('selected');
                } else {
                    selectedNotes.add(noteId);
                    noteCard.classList.add('selected');
                }
                updateSelectionUI();
            }
        }

        async function deleteNote(id) {
            if (confirm('Delete this note?')) {
                const noteElement = document.querySelector(`[data-id="${id}"]`);
                notesMap.delete(id);
                selectedNotes.delete(id);
                if (noteElement) {
                    noteElement.classList.add('deleting');
                    setTimeout(() => {
                        noteElement.remove();
                        updateSelectionUI();
                    }, 300);
                } else {
                    updateSelectionUI();
                }
                if (IS_AUTHENTICATED) {
                    try {
                        const response = await fetch(`${AUTH_API_URL}?action=notes&id=${id}`, {
                            method: 'DELETE',
                            credentials: 'same-origin'
                        });
                        if (!response.ok) console.error('Failed to delete note from server');
                    } catch (error) {
                        console.error('Error deleting note:', error);
                    }
                }
            }
        }

        async function duplicateNote(id) {
            const original = notesMap.get(id);
            if (original) {
                let duplicate;
                if (IS_AUTHENTICATED) {
                    try {
                        duplicate = await auth.createNote({
                            title: original.title + ' (Copy)',
                            content: original.content,
                            type: original.type,
                            urgent: original.urgent,
                            date: new Date().toLocaleDateString()
                        });
                    } catch (error) {
                        console.error('Error duplicating note:', error);
                        return;
                    }
                } else {
                    duplicate = {
                        ...original,
                        id: noteIdCounter++,
                        title: original.title + ' (Copy)',
                        date: new Date().toLocaleDateString()
                    };
                }
                notesMap.set(duplicate.id, duplicate);
                renderNotes();
            }
        }

        function exportAsJSON() {
            const notes = Array.from(notesMap.values());
            const blob = new Blob([JSON.stringify(notes, null, 2)], { type: 'application/json' });
            downloadFile(blob, 'zornell_notes.json');
        }

        function exportAsText() {
            let text = 'ZORNELL NOTES\n' + '='.repeat(50) + '\n\n';
            notesMap.forEach(note => {
                text += `[${note.type.toUpperCase()}${note.urgent ? ' - URGENT' : ''}] ${note.date}\n`;
                text += note.title + '\n';
                text += '-'.repeat(30) + '\n';
                text += note.content + '\n';
                text += '\n\n';
            });
            const blob = new Blob([text], { type: 'text/plain' });
            downloadFile(blob, 'zornell_notes.txt');
        }

        function exportAsMarkdown() {
            let markdown = '# ZORNELL NOTES\n\n';
            const notesByType = { urgent: [], work: [], personal: [] };
            notesMap.forEach(note => {
                if (note.urgent) notesByType.urgent.push(note);
                else notesByType[note.type].push(note);
            });
            if (notesByType.urgent.length > 0) {
                markdown += '## 🚨 URGENT\n\n';
                notesByType.urgent.forEach(note => {
                    markdown += `### ${note.title}\n`;
                    markdown += `*${note.date} - ${note.type}*\n\n`;
                    markdown += `${note.content}\n\n---\n\n`;
                });
            }
            if (notesByType.work.length > 0) {
                markdown += '## 💼 WORK\n\n';
                notesByType.work.forEach(note => {
                    markdown += `### ${note.title}\n`;
                    markdown += `*${note.date}*\n\n`;
                    markdown += `${note.content}\n\n---\n\n`;
                });
            }
            if (notesByType.personal.length > 0) {
                markdown += '## 🏠 PERSONAL\n\n';
                notesByType.personal.forEach(note => {
                    markdown += `### ${note.title}\n`;
                    markdown += `*${note.date}*\n\n`;
                    markdown += `${note.content}\n\n---\n\n`;
                });
            }
            const blob = new Blob([markdown], { type: 'text/markdown' });
            downloadFile(blob, 'zornell_notes.md');
        }

        function downloadFile(blob, filename) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        }

        async function saveNotes() {
            if (IS_AUTHENTICATED) await syncToServer();
        }

        async function showAuthForm() {
            const authContainer = createAuthForm();
            document.body.appendChild(authContainer);
            const form = document.getElementById('authForm');
            const emailInput = document.getElementById('authEmail');
            const passwordInput = document.getElementById('authPassword');
            const loginBtn = document.getElementById('loginBtn');
            const registerBtn = document.getElementById('registerBtn');
            const errorDiv = document.getElementById('authError');
            async function handleAuth(isRegister) {
                errorDiv.textContent = '';
                const email = emailInput.value;
                const password = passwordInput.value;
                try {
                    if (isRegister) {
                        await auth.register(email, password);
                        errorDiv.style.color = '#0ff';
                        errorDiv.textContent = 'Registration successful! Please login.';
                        emailInput.value = '';
                        passwordInput.value = '';
                    } else {
                        await auth.login(email, password);
                        authContainer.remove();
                        document.querySelector('.container').style.display = '';
                        await loadUserNotes();
                        updateUserUI();
                        startSync();
                    }
                } catch (error) {
                    errorDiv.style.color = '#f00';
                    errorDiv.textContent = error.message;
                }
            }
            form.onsubmit = (e) => {
                e.preventDefault();
                handleAuth(false);
            };
            registerBtn.onclick = () => handleAuth(true);
        }

        async function loadUserNotes() {
            try {
                const existingNotes = document.querySelectorAll('.note-card:not(.add-note-placeholder)');
                if (existingNotes.length > 0) {
                    existingNotes.forEach(noteEl => {
                        const id = noteEl.dataset.id;
                        const titleEl = noteEl.querySelector('.note-title');
                        const contentEl = noteEl.querySelector('.note-content');
                        const typeTag = noteEl.querySelector('.tag:not(.urgent)');
                        const urgentTag = noteEl.querySelector('.tag.urgent');
                        const dateEl = noteEl.querySelector('.note-date');
                        const note = {
                            id: id,
                            title: titleEl ? titleEl.textContent : '',
                            content: contentEl ? contentEl.textContent : '',
                            type: typeTag ? typeTag.textContent.toLowerCase() : 'personal',
                            urgent: !!urgentTag,
                            date: dateEl ? dateEl.textContent : new Date().toLocaleDateString()
                        };
                        notesMap.set(id, note);
                    });
                    attachNoteHandlers();
                } else {
                    const serverNotes = await auth.fetchNotes();
                    notesMap.clear();
                    if (Array.isArray(serverNotes)) {
                        serverNotes.forEach(note => {
                            if (note.title || note.content) notesMap.set(note.id, note);
                        });
                    }
                    renderNotes();
                }
            } catch (error) {
                console.error('Failed to load notes:', error);
            }
        }

        function attachNoteHandlers() {
            document.querySelectorAll('.note-card:not(.add-note-placeholder)').forEach(noteEl => {
                const id = noteEl.dataset.id;
                if (!noteEl.onclick) noteEl.onclick = (e) => handleNoteClick(e, id);
            });
        }

        async function syncToServer() {
            if (!auth || !auth.isAuthenticated()) return;
            try {
                const notes = Array.from(notesMap.values());
                await auth.syncNotes(notes);
            } catch (error) {
                if (error.message.includes('Session expired')) showAuthForm();
            }
        }

        function startSync() {
            syncInterval = setInterval(syncToServer, 30000);
        }

        function updateUserUI() {
            const userInfo = document.getElementById('userInfo');
            if (IS_AUTHENTICATED) userInfo.style.display = 'flex';
            else userInfo.style.display = 'none';
        }

        let contentObserver = null;

        function setupLazyLoading() {
            if ('IntersectionObserver' in window) {
                contentObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const noteCard = entry.target;
                            const contentEl = noteCard.querySelector('.note-content');
                            if (contentEl && contentEl.classList.contains('loading')) {
                                const noteId = noteCard.dataset.id;
                                const note = notesMap.get(noteId);
                                if (note) {
                                    contentEl.textContent = note.content;
                                    contentEl.classList.remove('loading');
                                }
                            }
                            contentObserver.unobserve(noteCard);
                        }
                    });
                }, { rootMargin: '50px' });
                document.querySelectorAll('.note-card:not(.add-note-placeholder)').forEach(card => {
                    contentObserver.observe(card);
                });
            }
        }

        auth = new ZornellAuth();
        if (IS_AUTHENTICATED) {
            loadUserNotes();
            updateUserUI();
            startSync();
            requestAnimationFrame(() => setupLazyLoading());
        } else {
            showAuthForm();
        }

        const debouncedSearch = debounce(filterNotes, 150);
        document.getElementById('searchBox').addEventListener('input', debouncedSearch);

        function showExportMenu() {
            const menu = document.getElementById('exportMenu');
            const exportBtn = event.target;
            const isVisible = menu.style.display === 'block';
            menu.style.display = isVisible ? 'none' : 'block';
            exportBtn.classList.toggle('active', !isVisible);
        }

        document.addEventListener('click', (e) => {
            const menu = document.getElementById('exportMenu');
            const exportBtn = e.target.closest('.export-btn');
            if (!exportBtn && !menu.contains(e.target)) {
                menu.style.display = 'none';
                document.querySelector('.export-btn').classList.remove('active');
            }
        });

        function handleNoteClick(e, noteId) {
            const noteCard = e.currentTarget;
            requestAnimationFrame(() => {
                if ((e.ctrlKey || e.metaKey) && selectedNotes.size > 0) {
                    if (selectedNotes.has(noteId)) {
                        selectedNotes.delete(noteId);
                        noteCard.classList.remove('selected');
                    } else {
                        selectedNotes.add(noteId);
                        noteCard.classList.add('selected');
                    }
                    lastClickedNote = noteId;
                    updateSelectionUI();
                } else if (e.shiftKey && lastClickedNote !== null && selectedNotes.size > 0) {
                    selectRange(lastClickedNote, noteId);
                    updateSelectionUI();
                } else {
                    if (selectedNotes.size > 0) {
                        clearSelection();
                        updateSelectionUI();
                    }
                }
            });
        }

        function selectRange(startId, endId) {
            const noteElements = Array.from(document.querySelectorAll('.note-card:not(.add-note-placeholder)'));
            const noteIds = noteElements.map(el => el.dataset.id);
            const startIndex = noteIds.indexOf(startId);
            const endIndex = noteIds.indexOf(endId);
            if (startIndex === -1 || endIndex === -1) return;
            const minIndex = Math.min(startIndex, endIndex);
            const maxIndex = Math.max(startIndex, endIndex);
            clearSelection();
            for (let i = minIndex; i <= maxIndex; i++) {
                const id = noteIds[i];
                selectedNotes.add(id);
                noteElements[i].classList.add('selected');
            }
        }

        function clearSelection() {
            selectedNotes.clear();
            document.querySelectorAll('.note-card.selected').forEach(el => el.classList.remove('selected'));
        }

        function updateSelectionUI() {
            const deleteCount = document.getElementById('deleteCount');
            const searchBox = document.getElementById('searchBox');
            requestAnimationFrame(() => {
                if (selectedNotes.size > 0) {
                    document.body.classList.add('has-selection');
                    deleteCount.textContent = selectedNotes.size;
                    if (searchBox.value) {
                        searchBox.value = '';
                        renderNotes();
                    }
                } else {
                    document.body.classList.remove('has-selection');
                }
            });
        }

        async function deleteSelectedNotes() {
            if (selectedNotes.size === 0) return;
            const count = selectedNotes.size;
            const message = count === 1 ? 'Delete this note?' : `Delete ${count} notes?`;
            if (confirm(message)) {
                const noteElements = [];
                const idsToDelete = Array.from(selectedNotes);
                selectedNotes.forEach(id => {
                    const noteElement = document.querySelector(`[data-id="${id}"]`);
                    if (noteElement) {
                        noteElement.classList.add('deleting');
                        noteElements.push(noteElement);
                    }
                });
                selectedNotes.forEach(id => notesMap.delete(id));
                clearSelection();
                setTimeout(() => {
                    noteElements.forEach(el => el.remove());
                    updateSelectionUI();
                }, 300);
                if (IS_AUTHENTICATED) {
                    for (const id of idsToDelete) {
                        try {
                            await fetch(`${AUTH_API_URL}?action=notes&id=${id}`, {
                                method: 'DELETE',
                                credentials: 'same-origin'
                            });
                        } catch (error) {
                            console.error('Error deleting note:', error);
                        }
                    }
                }
            }
        }

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                if (e.target.contentEditable === 'true') return;
                e.preventDefault();
                selectAll();
            }
            if ((e.key === 'Delete' || e.key === 'Backspace') && selectedNotes.size > 0) {
                if (e.target.contentEditable === 'true' || e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
                e.preventDefault();
                deleteSelectedNotes();
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                clearSelection();
                updateSelectionUI();
                if (document.activeElement) document.activeElement.blur();
            }
        });

        function selectAll() {
            clearSelection();
            document.querySelectorAll('.note-card:not(.add-note-placeholder)').forEach(el => {
                const id = el.dataset.id;
                selectedNotes.add(id);
                el.classList.add('selected');
            });
            updateSelectionUI();
        }
    </script>
</body>
</html>

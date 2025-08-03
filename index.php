<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZORNELL - Fast Notes</title>
    <meta name="description" content="Fast, efficient note-taking app with multi-select and keyboard shortcuts">
    <script>
// Authentication module for Zornell
const AUTH_API_URL = '/backend/api.php';

class ZornellAuth {
    constructor() {
        this.token = localStorage.getItem('zornell_token');
        this.userEmail = localStorage.getItem('zornell_email');
    }

    isAuthenticated() {
        return !!this.token;
    }

    async register(email, password) {
        const response = await fetch(`${AUTH_API_URL}?action=register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Registration failed');
        }
        return data;
    }

    async login(email, password) {
        const response = await fetch(`${AUTH_API_URL}?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Login failed');
        }

        // Store auth data
        this.token = data.token;
        this.userEmail = data.email;
        localStorage.setItem('zornell_token', data.token);
        localStorage.setItem('zornell_email', data.email);

        return data;
    }

    async logout() {
        if (!this.token) return;

        try {
            await fetch(`${AUTH_API_URL}?action=logout`, {
                method: 'POST',
                headers: { 'Authorization': this.token }
            });
        } catch (e) {
            // Continue with local logout even if request fails
        }

        // Clear local auth data
        this.token = null;
        this.userEmail = null;
        localStorage.removeItem('zornell_token');
        localStorage.removeItem('zornell_email');
    }

    async syncNotes(notes) {
        if (!this.token) throw new Error('Not authenticated');

        const response = await fetch(`${AUTH_API_URL}?action=notes`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': this.token
            },
            body: JSON.stringify(notes)
        });

        if (response.status === 401) {
            // Token expired, logout
            await this.logout();
            throw new Error('Session expired. Please login again.');
        }

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Sync failed');
        }

        return data;
    }

    async fetchNotes() {
        if (!this.token) throw new Error('Not authenticated');

        const response = await fetch(`${AUTH_API_URL}?action=notes`, {
            headers: { 'Authorization': this.token }
        });

        if (response.status === 401) {
            // Token expired, logout
            await this.logout();
            throw new Error('Session expired. Please login again.');
        }

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Failed to fetch notes');
        }

        return data;
    }

    async createNote(noteData) {
        if (!this.token) throw new Error('Not authenticated');

        const response = await fetch(`${AUTH_API_URL}?action=create_note`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': this.token
            },
            body: JSON.stringify(noteData)
        });

        if (response.status === 401) {
            // Token expired, logout
            await this.logout();
            throw new Error('Session expired. Please login again.');
        }

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Failed to create note');
        }

        return data;
    }
}

// Create auth form HTML
function createAuthForm() {
    const div = document.createElement('div');
    div.className = 'auth-container';
    div.innerHTML = `
        <div class="auth-form">
            <h2>ZORNELL</h2>
            <p class="auth-subtitle">Lightning-fast notes that sync everywhere</p>
            
            <form id="authForm">
                <input type="email" id="authEmail" placeholder="Email" required autocomplete="off">
                <input type="password" id="authPassword" placeholder="Password" required minlength="8" autocomplete="off">
                
                <div class="auth-buttons">
                    <button type="submit" id="loginBtn">Login</button>
                    <button type="button" id="registerBtn">Register</button>
                </div>
                
                <div id="authError" class="auth-error"></div>
            </form>
            
            <div class="auth-footer">
                <div class="feature-list">
                    <p>✓ Capture thoughts in seconds, not minutes</p>
                    <p>✓ Works offline, syncs when connected</p>
                    <p>✓ Export to TXT, JSON, or Markdown</p>
                </div>
            </div>
        </div>
    `;
    return div;
}

// Export for use in main app
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

.search-box, .delete-selected-btn {
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

/* When notes are selected */
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
    /* Reduce layout thrashing */
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
    /* Reduce paint operations */
    will-change: transform;
    transform: translateZ(0);
    backface-visibility: hidden;
    /* Isolate rendering */
    contain: layout style paint;
    cursor: pointer;
    user-select: none;
    /* Force GPU acceleration */
    perspective: 1000px;
}

/* View transition for new notes - Matrix-style digital materialization */
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

/* View transition for deleting notes - Digital disintegration */
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

/* Static noise effect for deletion */
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
    background: repeating-linear-gradient(
        0deg,
        rgba(255, 0, 0, 0.1) 0px,
        transparent 1px,
        transparent 2px,
        rgba(255, 0, 0, 0.1) 3px
    );
    animation: staticNoise 0.3s steps(1);
    mix-blend-mode: multiply;
}

.note-card:hover {
    transform: translateY(-2px);
}

/* Personal notes selected (cyan) */
.note-card.personal.selected {
    border-color: #0ff;
    background-color: rgba(0, 255, 255, 0.15);
    box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
}

/* Work notes selected (magenta) */
.note-card.work.selected {
    border-color: #f0f;
    background-color: rgba(255, 0, 255, 0.15);
    box-shadow: 0 0 10px rgba(255, 0, 255, 0.3);
}

/* Urgent notes selected (red) - takes priority */
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
    border: 1px dashed #ff0 !important;
    background-color: rgba(255, 255, 0, 0.02) !important;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.add-note-placeholder:hover {
    background-color: rgba(255, 255, 0, 0.1) !important;
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
    
    .user-email {
        display: none; /* Hide email on mobile, keep logout */
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

/* Authentication Styles */
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

/* User section */
.user-section {
    display: flex;
    align-items: center;
    gap: 5px;
    position: relative;
}

/* User info display */
.user-info {
    display: flex;
    align-items: center;
    gap: 5px;
}

.user-email {
    color: #0ff;
    font-size: 12px;
}

.logout-btn {
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
}

.logout-btn:hover {
    background: #f00;
    color: #fff;
}
    </style>
</head>
<body>
    <div class="container">
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
                <div class="user-info" id="userInfo" style="display: none;">
                    <button class="logout-btn" onclick="logout()">LOGOUT</button>
                </div>
                <div class="export-menu" id="exportMenu" style="display: none;">
                <button class="export-option" onclick="exportAsJSON()">Export as JSON</button>
                <button class="export-option" onclick="exportAsText()">Export as TXT</button>
                <button class="export-option" onclick="exportAsMarkdown()">Export as Markdown</button>
                    <button class="export-option" onclick="window.print()">Print / PDF</button>
                </div>
            </div>
        </div>

        <div class="notes-container" id="notesContainer">
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
        // Store notes in memory for O(1) access
        const notesMap = new Map();
        let currentFilter = 'all';
        let noteIdCounter = 1;
        const selectedNotes = new Set();
        let lastClickedNote = null;
        let renderScheduled = false;
        let auth = null;
        let syncInterval = null;

        // Initialize with sample notes (only for non-authenticated users)
        function initSampleNotes() {
            if (auth && auth.isAuthenticated()) return;
            
            const sampleNotes = [
                {
                    id: noteIdCounter++,
                    title: 'Q1 2025 Planning',
                    content: '• Review Q4 results\n• Set new KPIs\n• Team expansion plans\n• Budget allocation',
                    type: 'work',
                    urgent: true,
                    date: new Date().toLocaleDateString()
                },
                {
                    id: noteIdCounter++,
                    title: 'Project Alpha Sprint',
                    content: '• Complete API refactoring\n• User testing feedback\n• Deploy to staging\n• Security audit scheduled',
                    type: 'work',
                    urgent: false,
                    date: new Date().toLocaleDateString()
                },
                {
                    id: noteIdCounter++,
                    title: 'Weekend Plans',
                    content: '• Saturday: Gym at 9am\n• Lunch with Sarah\n• Grocery shopping\n• Movie night',
                    type: 'personal',
                    urgent: false,
                    date: new Date().toLocaleDateString()
                },
                {
                    id: noteIdCounter++,
                    title: 'Client Meeting Notes',
                    content: 'Requirements:\n• Mobile-first design\n• Dark mode support\n• Real-time sync\n• Offline capability',
                    type: 'work',
                    urgent: true,
                    date: new Date().toLocaleDateString()
                },
                {
                    id: noteIdCounter++,
                    title: 'Reading List',
                    content: '• "Atomic Habits" - James Clear\n• "Deep Work" - Cal Newport\n• "The Pragmatic Programmer"\n• "Zero to One"',
                    type: 'personal',
                    urgent: false,
                    date: new Date().toLocaleDateString()
                },
                {
                    id: noteIdCounter++,
                    title: 'Quick Ideas',
                    content: '• AI-powered note categorization\n• Voice-to-text feature\n• Collaborative notes\n• Template system',
                    type: 'work',
                    urgent: false,
                    date: new Date().toLocaleDateString()
                },
                {
                    id: noteIdCounter++,
                    title: 'Travel Checklist',
                    content: '• Book flights ✓\n• Hotel reservation\n• Pack chargers\n• Check passport expiry',
                    type: 'personal',
                    urgent: true,
                    date: new Date().toLocaleDateString()
                },
                {
                    id: noteIdCounter++,
                    title: 'Team Standup',
                    content: 'Daily sync topics:\n• Blockers discussion\n• Sprint progress\n• Code review assignments\n• Testing status',
                    type: 'work',
                    urgent: false,
                    date: new Date().toLocaleDateString()
                }
            ];

            sampleNotes.forEach(note => notesMap.set(note.id, note));
        }

        async function addNewNote() {
            let note;
            
            // If authenticated, create note on server
            if (auth && auth.isAuthenticated()) {
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
                    // Fall back to local creation
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
                // For non-authenticated users, still use numeric IDs for sample notes
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
            
            // Optimize rendering for single note addition
            const container = document.getElementById('notesContainer');
            const placeholder = container.querySelector('.add-note-placeholder');
            const noteElement = createNoteElement(note);
            
            // Insert new note before placeholder with animation
            container.insertBefore(noteElement, placeholder);
            
            // Trigger reflow for animation
            noteElement.offsetHeight;
            noteElement.classList.add('new-note');
            
            // Remove animation class after completion
            setTimeout(() => {
                noteElement.classList.remove('new-note');
                delete note.isNew;
            }, 400);
            
            // Focus on the new note's title
            requestAnimationFrame(() => {
                const titleElement = noteElement.querySelector('.note-title');
                if (titleElement) {
                    titleElement.focus();
                    // Select all text for easy replacement
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

        // Debounce function for search
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
            
            // Use requestAnimationFrame for smooth rendering
            requestAnimationFrame(() => {
                renderScheduled = false;
                const container = document.getElementById('notesContainer');
                const searchTerm = document.getElementById('searchBox').value.toLowerCase();
                
                // Convert Map to array and filter
                const notesArray = Array.from(notesMap.values());
                
                const filteredNotes = notesArray.filter(note => {
                    // Filter by type
                    let matchesFilter = currentFilter === 'all';
                    if (currentFilter === 'work') matchesFilter = note.type === 'work';
                    if (currentFilter === 'personal') matchesFilter = note.type === 'personal';
                    if (currentFilter === 'urgent') matchesFilter = note.urgent === true;
                    
                    // Filter by search - optimized with early returns
                    if (!matchesFilter) return false;
                    
                    const matchesSearch = !searchTerm || 
                        note.title.toLowerCase().includes(searchTerm) || 
                        note.content.toLowerCase().includes(searchTerm);
                    
                    return matchesSearch;
                });

                // Use DocumentFragment for batch DOM updates
                const fragment = document.createDocumentFragment();
            
                // Reuse existing elements where possible
                const existingElements = new Map();
                container.querySelectorAll('.note-card:not(.add-note-placeholder)').forEach(el => {
                    existingElements.set(el.dataset.id, el);
                });
                
                // Don't use innerHTML = '' as it removes all event handlers
                // Instead, remove all children except placeholder
                const placeholder = container.querySelector('.add-note-placeholder');
                if (placeholder) {
                    placeholder.remove();
                }
                
                filteredNotes.forEach(note => {
                    let noteCard = existingElements.get(note.id);
                    if (noteCard) {
                        // Update existing element classes
                        noteCard.className = `note-card ${note.type}${note.urgent ? ' urgent' : ''}${selectedNotes.has(note.id) ? ' selected' : ''}`;
                        existingElements.delete(note.id);
                    } else {
                        // Create new element
                        noteCard = createNoteElement(note);
                    }
                    fragment.appendChild(noteCard);
                });
                
                // Remove unused elements
                existingElements.forEach(el => el.remove());
                
                // Add all filtered notes
                container.appendChild(fragment);
                
                // Add placeholder last
                const newPlaceholder = document.createElement('div');
                newPlaceholder.className = 'note-card add-note-placeholder';
                newPlaceholder.onclick = addNewNote;
                newPlaceholder.innerHTML = `
                    <div class="placeholder-content">
                        <span class="plus-icon">+</span>
                        <span class="placeholder-text">New Note</span>
                    </div>
                `;
                container.appendChild(newPlaceholder);
            });
        }

        function createNoteElement(note) {
            const div = document.createElement('div');
            div.className = `note-card ${note.type}${note.urgent ? ' urgent' : ''}${selectedNotes.has(note.id) ? ' selected' : ''}`;
            div.dataset.id = note.id;
            
            // Add click handler for selection
            div.onclick = (e) => handleNoteClick(e, note.id);
            
            div.innerHTML = `
                <div class="note-header">
                    <div class="note-title" contenteditable="true" spellcheck="true" autocomplete="on" autocorrect="on" autocapitalize="on" onfocus="clearDefaultText(this, 'New Note')" onblur="updateNote('${note.id}', 'title', this.textContent)" onmousedown="handleContentClick(event, '${note.id}')" onclick="event.stopPropagation()">${note.title}</div>
                    <div class="note-meta">
                        <span class="tag ${note.type}">${note.type.toUpperCase()}</span>
                        ${note.urgent ? '<span class="tag urgent">URGENT</span>' : ''}
                    </div>
                </div>
                <div class="note-content" contenteditable="true" spellcheck="true" autocomplete="on" autocorrect="on" autocapitalize="sentences" onfocus="clearDefaultText(this, 'Start typing...')" onblur="updateNote('${note.id}', 'content', this.innerText)" onmousedown="handleContentClick(event, '${note.id}')" onclick="event.stopPropagation()">${note.content}</div>
                <div class="note-footer">
                    <span class="note-date">${note.date}</span>
                    <div class="note-actions">
                        <button class="action-btn" onclick="toggleType(event, '${note.id}')">${note.type === 'work' ? 'PERSONAL' : 'WORK'}</button>
                        <button class="action-btn" onclick="toggleUrgent(event, '${note.id}')">URGENT</button>
                        <button class="action-btn" onclick="event.stopPropagation(); deleteNote('${note.id}')">DELETE</button>
                    </div>
                </div>
            `;
            
            return div;
        }

        function clearDefaultText(element, defaultText) {
            if (element.textContent === defaultText) {
                element.textContent = '';
            }
        }

        async function updateNote(id, field, value) {
            const note = notesMap.get(id);
            if (note) {
                note[field] = value;
                
                // Update on server if authenticated
                if (auth && auth.isAuthenticated()) {
                    try {
                        const response = await fetch(`${AUTH_API_URL}?action=note&id=${id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Authorization': auth.token
                            },
                            body: JSON.stringify({
                                title: note.title,
                                content: note.content,
                                type: note.type,
                                urgent: note.urgent
                            })
                        });
                        
                        if (!response.ok) {
                            console.error('Failed to update note on server');
                        }
                    } catch (error) {
                        console.error('Error updating note:', error);
                    }
                }
            }
        }

        async function toggleType(event, id) {
            event.stopPropagation();
            const note = notesMap.get(id);
            if (note) {
                const oldType = note.type;
                note.type = note.type === 'work' ? 'personal' : 'work';
                // Update only this note instead of re-rendering all
                const noteElement = document.querySelector(`[data-id="${id}"]`);
                if (noteElement) {
                    // Update note card classes
                    noteElement.classList.remove(oldType);
                    noteElement.classList.add(note.type);
                    
                    // Update type tag
                    const typeTag = noteElement.querySelector('.tag:not(.urgent)');
                    typeTag.textContent = note.type.toUpperCase();
                    typeTag.className = `tag ${note.type}`;
                    
                    // Update action button - specifically the first one (type toggle)
                    const actionBtns = noteElement.querySelectorAll('.action-btn');
                    if (actionBtns[0]) {
                        actionBtns[0].textContent = note.type === 'work' ? 'PERSONAL' : 'WORK';
                    }
                }
                
                // Update on server if authenticated
                if (auth && auth.isAuthenticated()) {
                    try {
                        await fetch(`${AUTH_API_URL}?action=note&id=${id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Authorization': auth.token
                            },
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
                
                // Update only this note instead of re-rendering all
                const noteElement = document.querySelector(`[data-id="${id}"]`);
                if (noteElement) {
                    // Update urgent class
                    if (note.urgent) {
                        noteElement.classList.add('urgent');
                        // Add urgent tag if not exists
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
                        // Remove urgent tag
                        const urgentTag = noteElement.querySelector('.tag.urgent');
                        if (urgentTag) {
                            urgentTag.remove();
                        }
                    }
                }
                
                // Update on server if authenticated
                if (auth && auth.isAuthenticated()) {
                    try {
                        await fetch(`${AUTH_API_URL}?action=note&id=${id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Authorization': auth.token
                            },
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
            // If ctrl/cmd is held and ANY notes are selected, prevent focus
            if ((event.ctrlKey || event.metaKey) && selectedNotes.size > 0) {
                event.preventDefault();
                event.stopPropagation();
                
                // Toggle selection
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
                
                // Delete from map immediately
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
                
                // Delete from server if authenticated
                if (auth && auth.isAuthenticated()) {
                    try {
                        const response = await fetch(`${AUTH_API_URL}?action=notes&id=${id}`, {
                            method: 'DELETE',
                            headers: { 'Authorization': auth.token }
                        });
                        
                        if (!response.ok) {
                            console.error('Failed to delete note from server');
                        }
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
                
                // If authenticated, create duplicate on server
                if (auth && auth.isAuthenticated()) {
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
                    // For non-authenticated users
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
            
            // Group notes by type
            const notesByType = {
                urgent: [],
                work: [],
                personal: []
            };
            
            notesMap.forEach(note => {
                if (note.urgent) {
                    notesByType.urgent.push(note);
                } else {
                    notesByType[note.type].push(note);
                }
            });
            
            // Export urgent notes first
            if (notesByType.urgent.length > 0) {
                markdown += '## 🚨 URGENT\n\n';
                notesByType.urgent.forEach(note => {
                    markdown += `### ${note.title}\n`;
                    markdown += `*${note.date} - ${note.type}*\n\n`;
                    markdown += `${note.content}\n\n---\n\n`;
                });
            }
            
            // Export work notes
            if (notesByType.work.length > 0) {
                markdown += '## 💼 WORK\n\n';
                notesByType.work.forEach(note => {
                    markdown += `### ${note.title}\n`;
                    markdown += `*${note.date}*\n\n`;
                    markdown += `${note.content}\n\n---\n\n`;
                });
            }
            
            // Export personal notes
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
            // Only do full sync when creating new notes
            if (auth && auth.isAuthenticated()) {
                await syncToServer();
            }
        }

        function loadSampleNotes() {
            initSampleNotes();
            renderNotes();
        }

        // Authentication functions
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
                const serverNotes = await auth.fetchNotes();
                notesMap.clear();
                
                // Only add notes if they have actual content
                if (Array.isArray(serverNotes)) {
                    serverNotes.forEach(note => {
                        // Skip empty notes
                        if (note.title || note.content) {
                            notesMap.set(note.id, note);
                        }
                    });
                }
                
                renderNotes();
            } catch (error) {
                console.error('Failed to load notes:', error);
            }
        }
        
        async function syncToServer() {
            if (!auth || !auth.isAuthenticated()) return;
            
            try {
                const notes = Array.from(notesMap.values());
                await auth.syncNotes(notes);
            } catch (error) {
                if (error.message.includes('Session expired')) {
                    showAuthForm();
                }
            }
        }
        
        function startSync() {
            // Sync every 30 seconds
            syncInterval = setInterval(syncToServer, 30000);
        }
        
        function updateUserUI() {
            const userInfo = document.getElementById('userInfo');
            
            if (auth && auth.isAuthenticated()) {
                userInfo.style.display = 'flex';
            } else {
                userInfo.style.display = 'none';
            }
        }
        
        async function logout() {
            if (syncInterval) {
                clearInterval(syncInterval);
            }
            await auth.logout();
            notesMap.clear();
            noteIdCounter = 1;
            // Clear local storage
            initSampleNotes();
            renderNotes();
            updateUserUI();
            showAuthForm();
        }
        
        // Initialize
        auth = new ZornellAuth();
        
        if (auth.isAuthenticated()) {
            loadUserNotes();
            updateUserUI();
            startSync();
        } else {
            loadSampleNotes();
            showAuthForm();
        }

        // Removed auto-save since we now save immediately on each change

        // Debounced search
        const debouncedSearch = debounce(filterNotes, 150);
        document.getElementById('searchBox').addEventListener('input', debouncedSearch);

        // Export menu functionality
        function showExportMenu() {
            const menu = document.getElementById('exportMenu');
            const exportBtn = event.target;
            const isVisible = menu.style.display === 'block';
            
            menu.style.display = isVisible ? 'none' : 'block';
            exportBtn.classList.toggle('active', !isVisible);
        }

        // Close export menu when clicking outside
        document.addEventListener('click', (e) => {
            const menu = document.getElementById('exportMenu');
            const exportBtn = e.target.closest('.export-btn');
            if (!exportBtn && !menu.contains(e.target)) {
                menu.style.display = 'none';
                document.querySelector('.export-btn').classList.remove('active');
            }
        });

        // Multi-select functionality
        function handleNoteClick(e, noteId) {
            const noteCard = e.currentTarget;
            
            // Batch DOM operations
            requestAnimationFrame(() => {
                if ((e.ctrlKey || e.metaKey) && selectedNotes.size > 0) {
                    // Ctrl/Cmd+Click: Toggle selection ONLY if there are already selected notes
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
                    // Shift+Click: Select range ONLY if there are already selected notes
                    selectRange(lastClickedNote, noteId);
                    updateSelectionUI();
                } else {
                    // Regular click: Clear any selection if exists
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
            document.querySelectorAll('.note-card.selected').forEach(el => {
                el.classList.remove('selected');
            });
        }

        function updateSelectionUI() {
            const deleteCount = document.getElementById('deleteCount');
            const searchBox = document.getElementById('searchBox');
            
            requestAnimationFrame(() => {
                if (selectedNotes.size > 0) {
                    document.body.classList.add('has-selection');
                    deleteCount.textContent = selectedNotes.size;
                    // Clear search when entering selection mode
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
                // Collect note elements before deleting from map
                const noteElements = [];
                const idsToDelete = Array.from(selectedNotes);
                
                selectedNotes.forEach(id => {
                    const noteElement = document.querySelector(`[data-id="${id}"]`);
                    if (noteElement) {
                        noteElement.classList.add('deleting');
                        noteElements.push(noteElement);
                    }
                });
                
                // Delete from map
                selectedNotes.forEach(id => {
                    notesMap.delete(id);
                });
                
                // Clear selection immediately
                clearSelection();
                
                // Remove elements after animation
                setTimeout(() => {
                    noteElements.forEach(el => el.remove());
                    updateSelectionUI();
                }, 300);
                
                // Delete from server if authenticated
                if (auth && auth.isAuthenticated()) {
                    // Delete each note individually
                    for (const id of idsToDelete) {
                        try {
                            await fetch(`${AUTH_API_URL}?action=notes&id=${id}`, {
                                method: 'DELETE',
                                headers: { 'Authorization': auth.token }
                            });
                        } catch (error) {
                            console.error('Error deleting note:', error);
                        }
                    }
                }
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd+A: Select all
            if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                // If focus is on contenteditable, let browser handle text selection
                if (e.target.contentEditable === 'true') {
                    return; // Let browser handle text selection
                }
                e.preventDefault();
                selectAll();
            }
            
            // Delete key: Delete selected notes
            if ((e.key === 'Delete' || e.key === 'Backspace') && selectedNotes.size > 0) {
                // Don't delete if user is editing text
                if (e.target.contentEditable === 'true' || 
                    e.target.tagName === 'INPUT' || 
                    e.target.tagName === 'TEXTAREA') {
                    return;
                }
                e.preventDefault();
                deleteSelectedNotes();
            }
            
            // Escape: Clear selection
            if (e.key === 'Escape') {
                e.preventDefault();
                clearSelection();
                updateSelectionUI();
                // Also blur any focused element
                if (document.activeElement) {
                    document.activeElement.blur();
                }
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

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
        localStorage.removeItem('zornellNotes'); // Clear local notes
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
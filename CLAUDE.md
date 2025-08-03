# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Zornell is a lightweight, fast note-taking web application built with vanilla PHP, JavaScript, and SQLite. It features:
- Server-side PHP sessions with client-side JavaScript authentication
- Real-time note syncing for authenticated users
- Multi-select functionality with keyboard shortcuts
- Export capabilities (JSON, TXT, Markdown)
- Mobile-responsive design with a cyberpunk aesthetic

## Key Architecture

### Frontend (index.php)
- Single-page application with inline JavaScript
- Authentication state managed via PHP sessions and JavaScript
- Notes stored in memory using Map for O(1) access
- Multi-select functionality with Ctrl/Cmd+Click and Shift+Click
- Real-time sync every 30 seconds for authenticated users

### Backend (backend/api.php)
- RESTful API endpoints for authentication and note management
- SQLite database with WAL mode for better concurrency
- Session-based authentication with token generation
- Automatic database backups before major operations

### Database Schema (backend/schema.sql)
- `users`: Email/password authentication
- `notes`: User-specific notes with unique string IDs (format: note_timestamp_randomhex)
- `sessions`: Authentication tokens with expiration

## Development Commands

### Run Local Development Server
```bash
./run-local.sh
```
This script:
- Checks PHP and SQLite dependencies
- Initializes the database if needed
- Starts PHP built-in server on port 8000
- Opens browser automatically (macOS/Linux)

### Manual PHP Server
```bash
php -S localhost:8000
```

### Database Management
- Database location: `backend/data/zornell.db`
- Backups: `backend/data/backups/`
- Initialize: `php backend/init-db.php`

## API Endpoints

- `POST /backend/api.php?action=register` - User registration
- `POST /backend/api.php?action=login` - User login (sets PHP session)
- `GET /backend/api.php?action=notes` - Fetch user notes
- `POST /backend/api.php?action=notes` - Sync all notes
- `POST /backend/api.php?action=create_note` - Create single note
- `PUT /backend/api.php?action=note&id={id}` - Update note
- `DELETE /backend/api.php?action=notes&id={id}` - Delete note

## Important Implementation Details

1. **Authentication Flow**: Login creates both a database session and PHP session. The PHP session token is passed to JavaScript for API calls.

2. **Note IDs**: Use string IDs with format `note_timestamp_randomhex` for uniqueness across distributed systems.

3. **Real-time Updates**: Individual note updates (title, content, type, urgent) are sent immediately to the server for authenticated users.

4. **Performance**: DOM updates are batched using requestAnimationFrame. Note elements are reused when possible during filtering.

5. **Security**: All user inputs are sanitized, passwords are hashed with bcrypt, and sessions expire after 30 days.

## Testing Considerations

- No formal test framework is set up
- Manual testing through the UI
- Database can be reset by deleting `backend/data/zornell.db` and restarting the server
# ZORNELL - Fast Notes App

A lightweight, fast, and efficient note-taking web application with multi-select capabilities and keyboard shortcuts.

## Features

- **Fast Performance**: Built with vanilla JavaScript and PHP for minimal overhead
- **Multi-Select**: Select multiple notes for bulk operations
- **Keyboard Shortcuts**: Efficient navigation and actions
- **Categories**: Organize notes by Work, Personal, and Urgent tags
- **Search**: Real-time search functionality
- **Export/Import**: Backup and restore your notes
- **Secure**: Session-based authentication with bcrypt password hashing
- **Auto-Save**: Changes are saved automatically
- **Responsive**: Works on desktop and mobile devices

## Tech Stack

- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **Backend**: PHP 8.1
- **Database**: SQLite3 with WAL mode
- **Server**: Nginx
- **Authentication**: Session-based with 30-day tokens

## Installation

### Local Development

1. Clone the repository:
```bash
git clone https://github.com/yourusername/zornell.git
cd zornell
```

2. Initialize the database:
```bash
php backend/init-db.php
```

3. Run locally:
```bash
./run-local.sh
```

The app will be available at `http://localhost:8080`

### Docker

```bash
docker-compose up
```

### Production Deployment

See [deployment/README.md](deployment/README.md) for detailed server setup instructions.

## Project Structure

```
zornell/
├── index.php           # Main application file
├── style.css          # Styling
├── auth.js            # Authentication logic
├── backend/
│   ├── api.php        # API endpoints
│   ├── init-db.php    # Database initialization
│   ├── schema.sql     # Database schema
│   └── backup.sh      # Backup script
├── deployment/        # Deployment configurations
│   ├── nginx.conf     # Nginx configuration
│   ├── setup-server.sh # Server setup script
│   └── README.md      # Deployment guide
└── docker-compose.yml # Docker configuration
```

## API Endpoints

- `POST /backend/api.php?action=register` - Register new user
- `POST /backend/api.php?action=login` - User login
- `GET /backend/api.php?action=check-auth` - Check authentication
- `GET /backend/api.php?action=notes` - Get all notes
- `POST /backend/api.php?action=add` - Add new note
- `POST /backend/api.php?action=update` - Update note
- `POST /backend/api.php?action=delete` - Delete note
- `POST /backend/api.php?action=sync` - Sync multiple notes
- `GET /backend/api.php?action=export` - Export notes
- `GET /backend/api.php?action=health` - Health check

## Security Features

- Prepared statements for all database queries
- CSRF protection via tokens
- Password hashing with bcrypt
- Session-based authentication
- Automatic database backups
- Input validation and sanitization

## Performance

- Optimized for 10,000+ users on a single server
- SQLite WAL mode for concurrent access
- Minimal JavaScript dependencies
- Efficient DOM manipulation
- Lazy loading for large note lists

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- Built for speed and efficiency
- Inspired by the need for a fast, no-frills note-taking app
- Designed to work reliably on minimal infrastructure
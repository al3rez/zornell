#!/bin/bash

# Local development server for Zornell
# Runs PHP's built-in server with SQLite support

echo "🚀 Starting Zornell local development server..."

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP 8.1+ with SQLite support."
    echo "   Ubuntu/Debian: sudo apt install php-cli php-sqlite3"
    echo "   Mac: brew install php"
    echo "   Fedora: sudo dnf install php-cli php-pdo"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
r
echo "📦 PHP version: $PHP_VERSION"

# Check for SQLite extension
if ! php -m | grep -q sqlite3; then
    echo "❌ SQLite3 extension not found. Please install php-sqlite3"
    exit 1
fi

# Create data directory if it doesn't exist
mkdir -p backend/data/backups
echo "📁 Data directory ready"

# Initialize database if needed
if [ -f "src/init-db.php" ]; then
    echo "🗄️  Initializing database..."
    php src/init-db.php
fi

# Set permissions (may need sudo on some systems)
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    chmod -R 777 backend/data 2>/dev/null || {
        echo "⚠️  Could not set permissions. You may need to run: sudo chmod -R 777 backend/data"
    }
fi

# Kill any existing PHP server on port 8000
if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null 2>&1; then
    echo "🛑 Stopping existing server on port 8000..."
    kill $(lsof -Pi :8000 -sTCP:LISTEN -t) 2>/dev/null
    sleep 1
fi

# Start PHP built-in server
PORT=8000
echo "🌐 Starting server at http://localhost:$PORT"
echo "📝 Press Ctrl+C to stop the server"
echo ""
echo "🔐 Test accounts:"
echo "   - Register a new account to test"
echo "   - All data stored locally in backend/data/zornell.db"
echo ""

# Open browser (optional - comment out if you don't want auto-open)
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    open "http://localhost:$PORT" 2>/dev/null
elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
    # Linux
    xdg-open "http://localhost:$PORT" 2>/dev/null || echo "✨ Open http://localhost:$PORT in your browser"
else
    echo "✨ Open http://localhost:$PORT in your browser"
fi

# Start PHP server with proper document root
php -S localhost:$PORT -t public -c /dev/null << 'EOF'
<?php
// Custom router for PHP built-in server
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Handle API requests
if (strpos($uri, '/backend/api.php') === 0) {
    include dirname(__DIR__) . '/src/api.php';
    return true;
}

// Serve static files
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Serve the requested file as-is
}

// Default to index.php
include __DIR__ . '/index.php';
?>
EOF

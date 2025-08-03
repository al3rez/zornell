#!/bin/bash

# Simple local test server for Zornell

echo "🚀 Starting Zornell local test server..."

# Check dependencies
command -v php >/dev/null 2>&1 || { echo "❌ PHP is required. Please install PHP 8.1+"; exit 1; }

# Create data directories
mkdir -p backend/data/backups

# Set write permissions for SQLite
chmod -R 777 backend/data 2>/dev/null || echo "⚠️  Note: May need sudo for permissions"

# Initialize database if needed
echo "🗄️  Initializing database..."
php backend/init-db.php

# Kill existing server
pkill -f "php -S localhost:8000" 2>/dev/null

# Start server
echo ""
echo "🌐 Server starting at: http://localhost:8000"
echo "📱 Press Ctrl+C to stop"
echo ""

# Run PHP built-in server
php -S localhost:8000
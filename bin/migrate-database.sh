#!/bin/bash

# Zornell Database Migration Script
# Migrates SQLite database from /var/www to /var/lib following FHS standards

set -e  # Exit on error

echo "🚀 Zornell Database Migration Script"
echo "===================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ This script must be run as root (use sudo)"
    exit 1
fi

# Configuration
OLD_DB_PATH="/var/www/zornell/backend/data/zornell.db"
OLD_BACKUP_DIR="/var/www/zornell/backend/data/backups"
NEW_BASE_DIR="/var/lib/zornell"
NEW_DB_DIR="$NEW_BASE_DIR/database"
NEW_DB_PATH="$NEW_DB_DIR/zornell.db"
NEW_BACKUP_DIR="$NEW_BASE_DIR/backups"
NEW_LOG_DIR="$NEW_BASE_DIR/logs"

echo "📋 Migration Plan:"
echo "  From: $OLD_DB_PATH"
echo "  To:   $NEW_DB_PATH"
echo ""

# Create new directory structure
echo "📁 Creating directory structure..."
mkdir -p "$NEW_DB_DIR"
mkdir -p "$NEW_BACKUP_DIR"
mkdir -p "$NEW_LOG_DIR"

# Check if old database exists
if [ ! -f "$OLD_DB_PATH" ]; then
    echo "⚠️  No existing database found at $OLD_DB_PATH"
    echo "   This might be a fresh installation."
    
    # Check if new database already exists
    if [ -f "$NEW_DB_PATH" ]; then
        echo "✅ Database already exists at new location: $NEW_DB_PATH"
        echo "   No migration needed."
    else
        echo "📝 Creating new database at $NEW_DB_PATH"
        touch "$NEW_DB_PATH"
    fi
else
    # Backup before migration
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_FILE="$NEW_BACKUP_DIR/pre_migration_${TIMESTAMP}.db"
    
    echo "💾 Creating backup before migration..."
    cp "$OLD_DB_PATH" "$BACKUP_FILE"
    echo "   Backup saved to: $BACKUP_FILE"
    
    # Check if database is in use
    if lsof "$OLD_DB_PATH" >/dev/null 2>&1; then
        echo "⚠️  Database is currently in use!"
        echo "   Please stop your web server first:"
        echo "   systemctl stop nginx php8.1-fpm"
        exit 1
    fi
    
    # Migrate database
    echo "🔄 Migrating database..."
    
    # Use SQLite backup command for safe migration
    sqlite3 "$OLD_DB_PATH" ".backup '$NEW_DB_PATH'" || {
        echo "❌ Database migration failed!"
        echo "   Restoring from backup..."
        cp "$BACKUP_FILE" "$NEW_DB_PATH"
        exit 1
    }
    
    echo "✅ Database migrated successfully!"
    
    # Migrate existing backups
    if [ -d "$OLD_BACKUP_DIR" ]; then
        echo "📦 Migrating existing backups..."
        cp -r "$OLD_BACKUP_DIR"/* "$NEW_BACKUP_DIR/" 2>/dev/null || true
    fi
fi

# Set permissions
echo "🔒 Setting permissions..."
chown -R www-data:www-data "$NEW_BASE_DIR"
chmod 750 "$NEW_BASE_DIR"
chmod 750 "$NEW_DB_DIR"
chmod 750 "$NEW_BACKUP_DIR"
chmod 750 "$NEW_LOG_DIR"

if [ -f "$NEW_DB_PATH" ]; then
    chmod 640 "$NEW_DB_PATH"
fi

# Update systemd service if exists
if [ -f "/etc/systemd/system/zornell-backup.service" ]; then
    echo "🔧 Updating systemd service..."
    sed -i "s|/var/www/zornell|/var/lib/zornell|g" /etc/systemd/system/zornell-backup.service
    systemctl daemon-reload
fi

# Create symlink for backward compatibility (temporary)
echo "🔗 Creating compatibility symlink..."
if [ -f "$OLD_DB_PATH" ]; then
    mv "$OLD_DB_PATH" "${OLD_DB_PATH}.migrated"
fi
ln -sf "$NEW_DB_PATH" "$OLD_DB_PATH" 2>/dev/null || true

# Verify migration
echo ""
echo "🔍 Verifying migration..."

if [ -f "$NEW_DB_PATH" ]; then
    echo "✅ Database exists at new location"
    
    # Check database integrity
    if sqlite3 "$NEW_DB_PATH" "PRAGMA integrity_check;" | grep -q "ok"; then
        echo "✅ Database integrity check passed"
    else
        echo "❌ Database integrity check failed!"
        exit 1
    fi
    
    # Show database info
    echo ""
    echo "📊 Database Information:"
    echo -n "   Size: "
    du -h "$NEW_DB_PATH" | cut -f1
    echo -n "   Tables: "
    sqlite3 "$NEW_DB_PATH" "SELECT COUNT(*) FROM sqlite_master WHERE type='table';" 2>/dev/null || echo "N/A"
    
    if sqlite3 "$NEW_DB_PATH" "SELECT COUNT(*) FROM users;" >/dev/null 2>&1; then
        echo -n "   Users: "
        sqlite3 "$NEW_DB_PATH" "SELECT COUNT(*) FROM users;"
        echo -n "   Notes: "
        sqlite3 "$NEW_DB_PATH" "SELECT COUNT(*) FROM notes;" 2>/dev/null || echo "0"
    fi
else
    echo "❌ Database not found at new location!"
    exit 1
fi

echo ""
echo "🎉 Migration completed successfully!"
echo ""
echo "📝 Next steps:"
echo "1. Update your nginx configuration if needed"
echo "2. Restart your web services:"
echo "   systemctl restart php8.1-fpm nginx"
echo "3. Test your application"
echo "4. Once confirmed working, remove old data:"
echo "   rm -rf /var/www/zornell/backend/data"
echo ""
echo "💡 Tip: Consider setting up Litestream for real-time backups:"
echo "   https://litestream.io/getting-started/"
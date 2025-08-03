#!/bin/bash

# ZORNELL Database Fresh Start Script
# This script resets the database and populates it with seed data

echo "🔄 ZORNELL Database Fresh Start"
echo "================================"

# Set the database path
DB_PATH="./data/zornell.db"
BACKUP_DIR="./data/backups"

# Create necessary directories
mkdir -p ./data
mkdir -p ./data/backups

# Backup existing database if it exists
if [ -f "$DB_PATH" ]; then
    BACKUP_NAME="zornell_backup_$(date +%Y%m%d_%H%M%S).db"
    echo "📦 Backing up existing database to $BACKUP_DIR/$BACKUP_NAME"
    cp "$DB_PATH" "$BACKUP_DIR/$BACKUP_NAME"
    echo "🗑️  Removing existing database..."
    rm "$DB_PATH"
fi

# Create fresh database with schema
echo "🏗️  Creating fresh database..."
sqlite3 "$DB_PATH" < schema.sql

# Apply seed data
echo "🌱 Applying seed data..."
sqlite3 "$DB_PATH" < seed.sql

# Set proper permissions
chmod 644 "$DB_PATH"
chmod 755 ./data
chmod 755 ./data/backups

# Verify the database
echo "✅ Verifying database..."
echo "   Users count: $(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM users;")"
echo "   Notes count: $(sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM notes;")"

echo ""
echo "✨ Database fresh start complete!"
echo ""
echo "Test credentials:"
echo "  Email: al3rez@gmail.com"
echo "  Password: al3rezal3rez"
echo ""
echo "The database has been populated with 20 sample notes including:"
echo "  - Work notes (project planning, documentation, code reviews)"
echo "  - Personal notes (recipes, workouts, reading lists)"
echo "  - Urgent notes (deadlines, appointments, critical tasks)"
echo ""
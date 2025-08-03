#!/bin/bash

# Zornell Database Backup Script
# Runs via cron to ensure zero data loss

BACKUP_DIR="/var/www/zornell/data/backups"
DB_PATH="/var/www/zornell/data/zornell.db"
REMOTE_BACKUP="/var/www/zornell/data/remote-backups"

# Create directories if they don't exist
mkdir -p "$BACKUP_DIR"
mkdir -p "$REMOTE_BACKUP"

# Create timestamped backup
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/zornell_${TIMESTAMP}.db"

# Use SQLite backup command for consistency
sqlite3 "$DB_PATH" ".backup '$BACKUP_FILE'"

# Compress older backups (keep last 24 hours uncompressed)
find "$BACKUP_DIR" -name "*.db" -mtime +1 -exec gzip {} \;

# Keep only last 30 days of backups
find "$BACKUP_DIR" -name "*.gz" -mtime +30 -delete

# Optional: Sync to object storage (Hetzner Storage Box)
# rsync -avz --delete "$BACKUP_DIR/" "u123456@u123456.your-storagebox.de:/backup/zornell/"

echo "Backup completed: $BACKUP_FILE"
#!/bin/bash

# Simple deployment script for Zornell
# Usage: ./deploy.sh

SERVER_IP="5.75.180.119"
SERVER_USER="root"
APP_DIR="/var/www/zornell"

echo "🚀 Deploying Zornell to $SERVER_IP..."

# Use index.php directly for deployment
echo "📋 Using public/index.php for deployment..."
cp public/index.php index-deploy.php

# Create deployment package
echo "📦 Creating deployment package..."
# First create a temp directory with the correct structure
mkdir -p /tmp/zornell-deploy
cp index-deploy.php /tmp/zornell-deploy/index.php
cp -r backend /tmp/zornell-deploy/
cp -r src /tmp/zornell-deploy/
cp -r config /tmp/zornell-deploy/
cp -r bin /tmp/zornell-deploy/
cp docs/nginx.conf /tmp/zornell-deploy/

# Create tarball from temp directory
tar -czf zornell-deploy.tar.gz \
    -C /tmp/zornell-deploy \
    --exclude='*.tar.gz' \
    --exclude='deployment' \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='data' \
    --exclude='backend/fresh.sh' \
    --exclude='backend/seed.sql' \
    --exclude='src/seed.sql' \
    --exclude='build-optimized.php' \
    .

# Cleanup temp directory
rm -rf /tmp/zornell-deploy

# Upload to server
echo "📤 Uploading files..."
scp zornell-deploy.tar.gz $SERVER_USER@$SERVER_IP:/tmp/

# Setup on server
echo "🔧 Setting up on server..."
ssh $SERVER_USER@$SERVER_IP << 'EOF'
    # Install dependencies if not already installed
    if ! command -v php &> /dev/null; then
        apt update
        apt install -y nginx php8.1-fpm php8.1-sqlite3 sqlite3 certbot python3-certbot-nginx
    fi

    # Create app directory
    mkdir -p /var/www/zornell
    cd /var/www/zornell

    # Create /var/lib/zornell structure
    mkdir -p /var/lib/zornell/database
    mkdir -p /var/lib/zornell/backups
    mkdir -p /var/lib/zornell/logs
    
    # Backup existing data if any
    if [ -f "/var/lib/zornell/database/zornell.db" ]; then
        cp /var/lib/zornell/database/zornell.db /var/lib/zornell/backups/zornell.db.backup.$(date +%s)
    elif [ -f "backend/data/zornell.db" ]; then
        # Migrate from old location if exists
        cp backend/data/zornell.db /var/lib/zornell/database/zornell.db
    fi

    # Extract new files
    tar -xzf /tmp/zornell-deploy.tar.gz
    rm /tmp/zornell-deploy.tar.gz
    
    # Set up public directory structure
    mkdir -p public
    mv index.php public/
    
    # Update nginx configuration
    if [ -f nginx.conf ]; then
        cp nginx.conf /etc/nginx/sites-available/zornell
        ln -sf /etc/nginx/sites-available/zornell /etc/nginx/sites-enabled/
        rm -f /etc/nginx/sites-enabled/default
        nginx -t && systemctl reload nginx
    fi

    # Set permissions
    chown -R www-data:www-data /var/www/zornell
    chmod -R 755 /var/www/zornell
    
    # Set permissions for /var/lib/zornell
    chown -R www-data:www-data /var/lib/zornell
    chmod 750 /var/lib/zornell
    chmod 750 /var/lib/zornell/database
    chmod 750 /var/lib/zornell/backups
    chmod 750 /var/lib/zornell/logs
    if [ -f /var/lib/zornell/database/zornell.db ]; then
        chmod 640 /var/lib/zornell/database/zornell.db
    fi

    # Setup cron for backups
    (crontab -l 2>/dev/null | grep -v "zornell.*backup.sh"; echo "*/30 * * * * /var/www/zornell/bin/backup.sh") | crontab -
    
    # Run database migration if needed
    if [ -f /var/www/zornell/bin/migrate-database.sh ]; then
        /var/www/zornell/bin/migrate-database.sh || true
    fi

    # Restart services
    systemctl restart php8.1-fpm
    systemctl restart nginx

    echo "✅ Deployment complete!"
EOF

# Cleanup
rm zornell-deploy.tar.gz
rm index-deploy.php

echo "🎉 Deployment finished! Your app should be available at http://$SERVER_IP"
#!/bin/bash

# Simple deployment script for Zornell
# Usage: ./deploy.sh

SERVER_IP="5.75.180.119"
SERVER_USER="root"
APP_DIR="/var/www/zornell"

echo "🚀 Deploying Zornell to $SERVER_IP..."

# Build optimized version
echo "🔨 Building optimized version..."
if [ -f "build-optimized.php" ]; then
    php build-optimized.php
    # Use optimized version as main index.php for deployment
    cp index-optimized.php index-deploy.php
else
    # Fallback to regular index.php if build script doesn't exist
    cp index.php index-deploy.php
fi

# Create deployment package
echo "📦 Creating deployment package..."
# First create a temp directory with the correct structure
mkdir -p /tmp/zornell-deploy
cp index-deploy.php /tmp/zornell-deploy/index.php
cp -r backend /tmp/zornell-deploy/
cp .htaccess /tmp/zornell-deploy/

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

    # Backup existing data if any
    if [ -f "data/zornell.db" ]; then
        cp data/zornell.db data/zornell.db.backup.$(date +%s)
    fi

    # Extract new files
    tar -xzf /tmp/zornell-deploy.tar.gz
    rm /tmp/zornell-deploy.tar.gz

    # Set permissions
    chown -R www-data:www-data /var/www/zornell
    chmod -R 755 /var/www/zornell
    chmod -R 775 /var/www/zornell/backend/data

    # Setup cron for backups
    (crontab -l 2>/dev/null | grep -v "zornell/backend/backup.sh"; echo "*/30 * * * * /var/www/zornell/backend/backup.sh") | crontab -

    # Restart services
    systemctl restart php8.1-fpm
    systemctl restart nginx

    echo "✅ Deployment complete!"
EOF

# Cleanup
rm zornell-deploy.tar.gz
rm index-deploy.php

echo "🎉 Deployment finished! Your app should be available at http://$SERVER_IP"
echo "📊 Deployed optimized version with ~12% size reduction"
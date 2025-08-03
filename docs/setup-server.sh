#!/bin/bash

# Initial server setup script
# Run this on the fresh Hetzner VPS

echo "🚀 Setting up Zornell server..."

# Update system
apt update && apt upgrade -y

# Install required packages
apt install -y nginx php8.1-fpm php8.1-sqlite3 sqlite3 certbot python3-certbot-nginx git

# Create app directory
mkdir -p /var/www/zornell
cd /var/www/zornell

# Set up PHP-FPM
systemctl start php8.1-fpm
systemctl enable php8.1-fpm

# Configure Nginx
cp /tmp/nginx.conf /etc/nginx/sites-available/zornell
ln -s /etc/nginx/sites-available/zornell /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default

# Test nginx config
nginx -t

# Restart Nginx
systemctl restart nginx

# Set permissions
chown -R www-data:www-data /var/www/zornell
chmod -R 755 /var/www/zornell

# Create data directory
mkdir -p /var/www/zornell/backend/data/backups
chown -R www-data:www-data /var/www/zornell/backend/data
chmod -R 775 /var/www/zornell/backend/data

# Setup firewall
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "✅ Server setup complete!"
echo "📝 Next steps:"
echo "1. Deploy your app using ./deploy.sh"
echo "2. Point your domain to this server"
echo "3. Run: certbot --nginx -d your-domain.com"
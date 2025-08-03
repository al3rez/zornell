#!/bin/bash

# Quick fix for nginx configuration on the server
# Run this on the server as root

echo "🔧 Fixing nginx configuration for new directory structure..."

# Create the nginx configuration
cat > /etc/nginx/sites-available/zornell << 'EOF'
server {
    listen 80;
    server_name _;
    root /var/www/zornell/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Frontend
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # API endpoints
    location ~ ^/backend/api\.php {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/zornell/src/api.php;
        
        # Increase timeouts for large syncs
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    # Protect sensitive files
    location ~ /backend/data {
        deny all;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /(src|bin|config|resources|tests) {
        deny all;
    }

    # PHP processing
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
}
EOF

# Enable the site
ln -sf /etc/nginx/sites-available/zornell /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test and reload nginx
nginx -t && systemctl reload nginx

echo "✅ Nginx configuration updated!"
echo "🔍 Checking directory structure..."

# Ensure public directory exists
if [ ! -d "/var/www/zornell/public" ]; then
    echo "⚠️  Public directory not found. Creating it..."
    mkdir -p /var/www/zornell/public
    
    # Move index.php if it's in the root
    if [ -f "/var/www/zornell/index.php" ]; then
        mv /var/www/zornell/index.php /var/www/zornell/public/
        echo "✅ Moved index.php to public directory"
    fi
fi

# Set permissions
chown -R www-data:www-data /var/www/zornell
chmod -R 755 /var/www/zornell

echo "🎉 Done! Your site should now be accessible."
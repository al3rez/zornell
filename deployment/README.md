# Zornell Deployment Guide

## Server Details
- **IP**: 5.75.180.119
- **Type**: Hetzner CPX11 (2 cores, 2GB RAM, 40GB SSD)
- **OS**: Ubuntu 22.04

## Quick Deployment

### 1. Initial Server Setup (one time)
```bash
# SSH into server
ssh root@5.75.180.119

# Upload and run setup script
scp deployment/setup-server.sh deployment/nginx.conf root@5.75.180.119:/tmp/
ssh root@5.75.180.119 'bash /tmp/setup-server.sh'
```

### 2. Deploy Application
```bash
# From your local machine
./deploy.sh
```

### 3. Set Up SSL (after domain is pointed)
```bash
ssh root@5.75.180.119
certbot --nginx -d your-domain.com
```

## Architecture

### Backend
- **PHP 8.1** with SQLite3
- **SQLite** database with WAL mode for concurrency
- **Automatic backups** every 30 minutes via cron
- **Session-based auth** with 30-day tokens

### Security
- Prepared statements for all queries
- Password hashing with bcrypt
- CSRF protection via tokens
- Rate limiting (TODO)
- Database backups before major operations

### Data Protection
- **Local backups**: Every 30 minutes, kept for 30 days
- **Pre-sync backups**: Before any bulk update
- **WAL mode**: Prevents corruption during crashes
- **Transaction rollback**: On any sync errors

## Monitoring

Check health endpoint:
```bash
curl http://5.75.180.119/backend/api.php?action=health
```

View logs:
```bash
ssh root@5.75.180.119
tail -f /var/log/nginx/error.log
tail -f /var/log/php8.1-fpm.log
```

## Backup Recovery

If data loss occurs:
```bash
ssh root@5.75.180.119
cd /var/www/zornell/backend/data/backups
# List backups
ls -la
# Restore specific backup
cp zornell_20240115_120000.db ../zornell.db
chown www-data:www-data ../zornell.db
```

## Updates

To deploy updates:
1. Make changes locally
2. Run `./deploy.sh`
3. The script preserves the database

## Costs
- **Server**: €4.51/month (~$5)
- **Domain**: ~$12/year (optional)
- **Total**: Under $6/month

This setup can handle 10,000+ users with SQLite!
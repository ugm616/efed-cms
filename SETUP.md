# Efed CMS - Setup and Installation Guide

## Overview

Efed CMS is a comprehensive JSON-first content management system designed specifically for wrestling federations. It manages wrestlers, companies, divisions, events, matches, and tags with secure authentication, role-based access control, and optional two-factor authentication.

## System Requirements

### Server Requirements
- **PHP 8.1 or higher** with the following extensions:
  - `pdo_mysql` - Database connectivity
  - `json` - JSON processing
  - `session` - Session management
  - `openssl` - Secure random number generation
  - `hash` - Password hashing
  - `mbstring` - Multibyte string support
  - `filter` - Input validation

- **Apache 2.4+** with `mod_rewrite` enabled
- **MySQL 8.0+** or **MariaDB 10.5+**

### Database Requirements
- Database server with `utf8mb4` character set support
- User with `CREATE`, `DROP`, `INSERT`, `UPDATE`, `DELETE`, `SELECT` privileges
- Recommended: dedicated database for the CMS

## Installation Steps

### 1. Download and Extract

Clone or download the Efed CMS files to your web server directory:

```bash
git clone <repository-url> /path/to/your/webroot
cd /path/to/your/webroot
```

### 2. Database Setup

#### Option A: Manual Database Creation

1. Create a new database:
```sql
CREATE DATABASE efed_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Create a database user:
```sql
CREATE USER 'efed_cms_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON efed_cms.* TO 'efed_cms_user'@'localhost';
FLUSH PRIVILEGES;
```

3. Import the database schema:
```bash
mysql -u efed_cms_user -p efed_cms < schema.sql
```

#### Option B: Using phpMyAdmin

1. Create a new database named `efed_cms` with `utf8mb4_unicode_ci` collation
2. Import the `schema.sql` file through the Import interface
3. Create a user with full privileges for the database

### 3. Configuration

#### Environment Variables (Recommended)

Set the following environment variables in your system or hosting control panel:

```bash
export APP_ENV=production
export APP_KEY=your-32-character-secret-key-here
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=efed_cms
export DB_USER=efed_cms_user
export DB_PASS=your_secure_password
```

#### Direct Configuration (Alternative)

Edit `config.php` to set your database credentials and security settings:

```php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'efed_cms');
define('DB_USER', 'efed_cms_user');
define('DB_PASS', 'your_secure_password');

// Security configuration
define('APP_KEY', 'your-32-character-secret-key-here-change-me');
```

**⚠️ Important:** Generate a unique 32-character secret key for `APP_KEY`. This is critical for security.

### 4. File Permissions

Set appropriate file permissions:

```bash
# Make sure Apache can read all files
chmod -R 644 .
find . -type d -exec chmod 755 {} \;

# Ensure manifests directory is writable
chmod 755 manifests
```

### 5. Apache Configuration

Ensure your Apache virtual host or `.htaccess` file includes:

```apache
# Enable mod_rewrite
RewriteEngine On

# Allow .htaccess override
AllowOverride All

# Security headers (if not set globally)
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"
```

### 6. SSL/HTTPS Setup (Highly Recommended)

Configure SSL for your domain to enable secure sessions and 2FA:

1. Obtain an SSL certificate (Let's Encrypt, commercial CA, etc.)
2. Configure Apache with SSL
3. Force HTTPS redirects:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 7. Initial Setup

1. Visit your domain: `https://yourdomain.com`
2. You'll see the public interface with API documentation
3. Go to the admin interface: `https://yourdomain.com/admin`
4. Create the initial owner account using the seed form
5. Log in with your owner credentials

## Configuration Details

### Security Settings

The system includes multiple security features:

- **Password Hashing**: Uses Argon2id (preferred) or BCRYPT fallback
- **Session Security**: HTTP-only, secure, SameSite cookies
- **CSRF Protection**: Token-based protection for all forms
- **Role-Based Access**: 5 user levels (viewer → contributor → editor → admin → owner)
- **Optional 2FA**: TOTP-based two-factor authentication

### User Roles

1. **Viewer** (Level 1): Read-only access to data
2. **Contributor** (Level 2): Can create new content
3. **Editor** (Level 3): Can edit existing content and manage tags
4. **Admin** (Level 4): Can delete content and manage users (except owners)
5. **Owner** (Level 5): Full system access, can create other owners

### Environment Variables Reference

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `development` | Application environment (`development`/`production`) |
| `APP_KEY` | *(required)* | 32-character secret key for encryption |
| `DB_HOST` | `localhost` | Database server hostname |
| `DB_PORT` | `3306` | Database server port |
| `DB_NAME` | `efed_cms` | Database name |
| `DB_USER` | `root` | Database username |
| `DB_PASS` | *(empty)* | Database password |

## API Usage

### Public Endpoints

- **Manifests**: `/manifest/{entity}.json` (wrestlers, companies, divisions, events, matches)
- **Entity Lists**: `/api/{entity}` (with pagination, search, sorting)
- **Entity Details**: `/api/{entity}/{id}`

### Authentication Required

- **Create**: `POST /api/{entity}` (requires contributor role)
- **Update**: `PUT /api/{entity}/{id}` (requires editor role)
- **Delete**: `DELETE /api/{entity}/{id}` (requires admin role)

### Example API Calls

#### Get All Wrestlers
```bash
curl "https://yourdomain.com/api/wrestlers?page=1&limit=20"
```

#### Create a Wrestler (authenticated)
```bash
curl -X POST "https://yourdomain.com/api/wrestlers" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Cena",
    "active": true,
    "record_wins": 150,
    "record_losses": 45,
    "elo": 1850,
    "csrf_token": "your-csrf-token"
  }'
```

#### Get Wrestlers Manifest
```bash
curl "https://yourdomain.com/manifest/wrestlers.json"
```

## Troubleshooting

### Common Issues

#### 1. "Database connection failed"
- Check database credentials in `config.php`
- Ensure database server is running
- Verify database user has proper privileges
- Check firewall settings

#### 2. "Internal server error"
- Check Apache error logs: `tail -f /var/log/apache2/error.log`
- Ensure all PHP extensions are installed
- Verify file permissions are correct
- Check `.htaccess` syntax

#### 3. "CSRF token invalid"
- Clear browser cookies and cache
- Ensure JavaScript is enabled
- Check for clock synchronization issues

#### 4. "Permission denied" errors
- Verify file ownership: `chown -R www-data:www-data /path/to/cms`
- Check directory permissions: `chmod 755` for directories, `chmod 644` for files
- Ensure manifests directory is writable

### Debug Mode

Enable debug mode for development:

```php
// In config.php
define('APP_ENV', 'development');
define('APP_DEBUG', true);
```

**⚠️ Never enable debug mode in production!**

### Log Files

Check these log files for issues:
- Apache error log: `/var/log/apache2/error.log`
- Apache access log: `/var/log/apache2/access.log`
- PHP error log: `/var/log/php/error.log`

## Performance Optimization

### Caching

The system includes built-in caching:
- **HTTP Headers**: ETag, Last-Modified, Cache-Control
- **Static Manifests**: JSON files cached and rebuilt on changes
- **Database Queries**: Optimized with proper indexing

### Recommended Apache Settings

```apache
# Enable compression
LoadModule deflate_module modules/mod_deflate.so
<Location />
    SetOutputFilter DEFLATE
    SetEnvIfNoCase Request_URI \
        \.(?:gif|jpe?g|png)$ no-gzip dont-vary
</Location>

# Browser caching for static assets
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
</FilesMatch>
```

### Database Optimization

- Regular `OPTIMIZE TABLE` commands for large tables
- Monitor slow query log
- Consider read replicas for high-traffic deployments

## Security Hardening

### Production Checklist

- [ ] Use HTTPS with strong SSL configuration
- [ ] Set strong `APP_KEY` (32+ random characters)
- [ ] Use strong database passwords
- [ ] Enable fail2ban for brute force protection
- [ ] Regular security updates for server software
- [ ] Firewall configuration (allow only necessary ports)
- [ ] Regular database backups
- [ ] Monitor access logs for suspicious activity

### Security Headers

Add these headers in Apache configuration:

```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'"
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"
```

## Backup and Maintenance

### Database Backup

```bash
# Daily backup script
mysqldump -u efed_cms_user -p efed_cms > backup_$(date +%Y%m%d).sql

# Restore from backup
mysql -u efed_cms_user -p efed_cms < backup_20240101.sql
```

### File Backup

```bash
# Backup application files (excluding vendor/cache directories)
tar -czf efed_cms_backup_$(date +%Y%m%d).tar.gz \
    --exclude='manifests' \
    .
```

### Update Procedure

1. Backup database and files
2. Download new version
3. Compare `config.php` for new settings
4. Run any database migrations
5. Clear caches and rebuild manifests
6. Test functionality

## Support and Development

### System Information

Use this information when reporting issues:
- PHP version: `php -v`
- Database version: `mysql --version`
- Apache version: `apache2 -v`
- Extension list: `php -m`

### Development Setup

For development environments:

1. Set `APP_ENV=development` in config
2. Enable error reporting
3. Use local database with test data
4. Consider using phpMyAdmin for database management

### API Documentation

Full API documentation is available at `/` (public interface) with:
- Complete endpoint listing
- Example requests and responses
- Authentication requirements
- Rate limiting information

## License and Credits

Efed CMS is built with vanilla PHP, HTML, CSS, and JavaScript - no external dependencies required. The system follows modern security practices and web standards for maximum compatibility and security.

For additional support or custom development, please refer to the project documentation or contact the development team.
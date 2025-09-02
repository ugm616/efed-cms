# Efed CMS

A comprehensive JSON-first content management system for wrestling federations.

## Features

- **Complete Entity Management**: Wrestlers, Companies, Divisions, Events, Matches, and Tags
- **Secure Authentication**: Session-based auth with optional TOTP 2FA
- **Role-Based Access Control**: 5 user levels (viewer → contributor → editor → admin → owner)
- **RESTful API**: Full CRUD operations with JSON responses
- **Public Manifests**: Cached JSON exports for external consumption
- **Responsive Admin Interface**: Dark theme with mobile support
- **Production Ready**: Security hardened with no external dependencies

## Quick Start

1. **Requirements**: PHP 8.1+, MySQL 8+/MariaDB 10.5+, Apache with mod_rewrite
2. **Install**: Upload files to web directory
3. **Configure**: Set database credentials in `config.php`
4. **Setup Database**: Import `schema.sql`
5. **Access**: Visit `/admin` to create initial owner account

## Documentation

- **[Complete Setup Guide](SETUP.md)** - Detailed installation and configuration
- **[API Documentation](/)** - Live API docs at your domain root
- **[Admin Interface](/admin)** - Management interface

## API Endpoints

### Public Access
- `GET /manifest/{entity}.json` - Data manifests
- `GET /api/{entity}` - List entities with pagination/search
- `GET /api/{entity}/{id}` - Get specific entity

### Authenticated Access
- `POST /api/{entity}` - Create (contributor+)
- `PUT /api/{entity}/{id}` - Update (editor+)
- `DELETE /api/{entity}/{id}` - Delete (admin+)

### Authentication
- `POST /api/auth/login` - Login with email/password
- `POST /api/auth/2fa/verify` - Two-factor authentication
- `GET /api/csrf` - Get CSRF token

## Security Features

- Argon2id password hashing with BCRYPT fallback
- CSRF protection on all state-changing operations
- Secure HTTP-only session cookies
- Optional TOTP two-factor authentication
- Role-based access control with 5 levels
- SQL injection prevention with prepared statements
- XSS protection with output escaping

## Tech Stack

- **Backend**: Vanilla PHP 8.1+ (no frameworks)
- **Database**: MySQL 8+ / MariaDB 10.5+
- **Frontend**: Vanilla HTML5, CSS3, JavaScript ES6+
- **Server**: Apache 2.4+ with mod_rewrite
- **No Dependencies**: Complete standalone system

## Database Schema

- **wrestlers**: Individual performers with records and statistics
- **companies**: Wrestling organizations and promotions  
- **divisions**: Weight classes and competition categories
- **events**: Shows, pay-per-views, and wrestling events
- **matches**: Individual contests between wrestlers
- **tags**: Flexible categorization for all entities
- **users**: System users with role-based permissions

## File Structure

```
/
├── .htaccess              # Apache URL rewriting and security
├── config.php             # Configuration and constants
├── index.php              # Main API router
├── schema.sql             # Database structure and sample data
├── SETUP.md               # Detailed setup instructions
├── lib/                   # Core PHP libraries
│   ├── db.php             # Database abstraction layer
│   ├── security.php       # Security and validation utilities
│   ├── auth.php           # Authentication and authorization
│   ├── totp.php           # Two-factor authentication
│   └── validators.php     # Input validation for all entities
├── admin/                 # Admin interface
│   ├── index.html         # Single-page admin application
│   ├── styles.css         # Dark theme responsive styles
│   └── admin.js           # Admin interface functionality
├── public/                # Public interface
│   └── index.html         # API documentation and stats
├── manifests/             # Cached JSON exports (auto-generated)
└── assets/                # Static assets
```

## License

This project is open source. Built with modern web standards and security best practices.

## Support

For setup assistance, see the [detailed setup guide](SETUP.md) or check the live API documentation at your domain root.
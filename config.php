<?php

// Environment configuration
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
define('APP_DEBUG', APP_ENV === 'development');

// Security configuration
define('APP_KEY', $_ENV['APP_KEY'] ?? 'your-32-character-secret-key-here-change-me');
define('SESSION_SECURE', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
define('SESSION_LIFETIME', 3600); // 1 hour

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);
define('DB_NAME', $_ENV['DB_NAME'] ?? 'efed_cms');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// Paths
define('ROOT_PATH', __DIR__);
define('LIB_PATH', ROOT_PATH . '/lib');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// User roles (hierarchical - higher number = more permissions)
define('ROLE_VIEWER', 1);
define('ROLE_CONTRIBUTOR', 2);
define('ROLE_EDITOR', 3);
define('ROLE_ADMIN', 4);
define('ROLE_OWNER', 5);

// Role names mapping
const ROLE_NAMES = [
    ROLE_VIEWER => 'viewer',
    ROLE_CONTRIBUTOR => 'contributor', 
    ROLE_EDITOR => 'editor',
    ROLE_ADMIN => 'admin',
    ROLE_OWNER => 'owner'
];

// Cache settings
define('CACHE_MAX_AGE', 300); // 5 minutes

// Pagination defaults
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', SESSION_SECURE ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_lifetime', 0);

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');

// Auto-load lib files
spl_autoload_register(function ($class) {
    $file = LIB_PATH . '/' . strtolower($class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
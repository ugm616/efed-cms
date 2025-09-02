<?php

class Security {
    
    /**
     * Generate a secure CSRF token
     */
    public static function generateCSRFToken(): string {
        if (!isset($_SESSION['csrf_secret'])) {
            $_SESSION['csrf_secret'] = bin2hex(random_bytes(32));
        }
        
        $data = session_id() . $_SESSION['csrf_secret'];
        return hash_hmac('sha256', $data, APP_KEY);
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken(string $token): bool {
        if (!isset($_SESSION['csrf_secret'])) {
            return false;
        }
        
        $expected = self::generateCSRFToken();
        return hash_equals($expected, $token);
    }
    
    /**
     * Hash password using Argon2id (with BCRYPT fallback)
     */
    public static function hashPassword(string $password): string {
        // Try Argon2id first
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536, // 64 MB
                'time_cost' => 4,       // 4 iterations
                'threads' => 3,         // 3 threads
            ]);
        }
        
        // Fallback to BCRYPT
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12
        ]);
    }
    
    /**
     * Verify password hash
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    /**
     * Check if password needs rehashing
     */
    public static function needsRehash(string $hash): bool {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3,
            ]);
        }
        
        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => 12
        ]);
    }
    
    /**
     * Generate secure random bytes
     */
    public static function randomBytes(int $length): string {
        return random_bytes($length);
    }
    
    /**
     * Generate secure random string
     */
    public static function randomString(int $length): string {
        return bin2hex(self::randomBytes($length));
    }
    
    /**
     * Sanitize HTML input
     */
    public static function sanitizeHtml(string $input): string {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Sanitize for URLs
     */
    public static function sanitizeUrl(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    /**
     * Validate email
     */
    public static function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate URL
     */
    public static function validateUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Generate slug from string
     */
    public static function generateSlug(string $string): string {
        // Convert to lowercase
        $slug = strtolower($string);
        
        // Replace spaces and special characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        
        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');
        
        // Remove consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);
        
        return $slug;
    }
    
    /**
     * Validate slug format
     */
    public static function validateSlug(string $slug): bool {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
    
    /**
     * Rate limiting (simple implementation)
     */
    public static function checkRateLimit(string $key, int $maxAttempts = 5, int $timeWindow = 300): bool {
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        
        $now = time();
        $windowStart = $now - $timeWindow;
        
        // Clean old entries
        if (isset($_SESSION['rate_limits'][$key])) {
            $_SESSION['rate_limits'][$key] = array_filter(
                $_SESSION['rate_limits'][$key],
                fn($timestamp) => $timestamp > $windowStart
            );
        } else {
            $_SESSION['rate_limits'][$key] = [];
        }
        
        // Check if limit exceeded
        if (count($_SESSION['rate_limits'][$key]) >= $maxAttempts) {
            return false;
        }
        
        // Record this attempt
        $_SESSION['rate_limits'][$key][] = $now;
        return true;
    }
    
    /**
     * Secure session start
     */
    public static function startSecureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        }
    }
    
    /**
     * Destroy session securely
     */
    public static function destroySession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
            session_write_close();
            
            // Delete the session cookie
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
    }
    
    /**
     * Generate ETag for caching
     */
    public static function generateETag(string $content): string {
        return '"' . md5($content) . '"';
    }
    
    /**
     * Set cache headers
     */
    public static function setCacheHeaders(string $content, int $maxAge = CACHE_MAX_AGE): void {
        $etag = self::generateETag($content);
        $lastModified = gmdate('D, d M Y H:i:s', time()) . ' GMT';
        
        header("ETag: {$etag}");
        header("Last-Modified: {$lastModified}");
        header("Cache-Control: public, max-age={$maxAge}");
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        
        // Check if client has cached version
        $clientETag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $clientLastModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        
        if ($clientETag === $etag || $clientLastModified === $lastModified) {
            http_response_code(304);
            exit;
        }
    }
    
    /**
     * Prevent clickjacking
     */
    public static function setSecurityHeaders(): void {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        if (SESSION_SECURE) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Validate JSON input
     */
    public static function validateJson(string $json): bool {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Safe JSON decode
     */
    public static function safeJsonDecode(string $json, bool $assoc = true) {
        $data = json_decode($json, $assoc);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        return $data;
    }
    
    /**
     * Safe JSON encode
     */
    public static function safeJsonEncode($data, int $flags = JSON_UNESCAPED_UNICODE): string {
        $json = json_encode($data, $flags);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON encoding failed: ' . json_last_error_msg());
        }
        return $json;
    }
    
    /**
     * Check if request is from AJAX
     */
    public static function isAjaxRequest(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIp(): string {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
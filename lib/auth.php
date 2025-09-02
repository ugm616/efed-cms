<?php

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }
    
    /**
     * Authenticate user with email and password
     */
    public function login(string $email, string $password): array {
        // Rate limiting
        if (!Security::checkRateLimit('login_' . Security::getClientIp(), 5, 300)) {
            throw new Exception('Too many login attempts. Please try again later.');
        }
        
        $user = $this->db->fetch(
            'SELECT * FROM users WHERE email = :email',
            ['email' => $email]
        );
        
        if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
            throw new Exception('Invalid email or password.');
        }
        
        // Check if password needs rehashing
        if (Security::needsRehash($user['password_hash'])) {
            $newHash = Security::hashPassword($password);
            $this->db->update('users', ['password_hash' => $newHash], 'id = :id', ['id' => $user['id']]);
        }
        
        // Check if 2FA is enabled
        if (!empty($user['twofa_secret'])) {
            // Store partial login state
            $_SESSION['partial_login'] = [
                'user_id' => $user['id'],
                'expires' => time() + 300 // 5 minutes to complete 2FA
            ];
            
            return [
                'requires_2fa' => true,
                'user_id' => $user['id']
            ];
        }
        
        // Complete login
        $this->completeLogin($user);
        
        return [
            'success' => true,
            'user' => $this->formatUserForResponse($user)
        ];
    }
    
    /**
     * Verify 2FA token and complete login
     */
    public function verify2FA(string $token): array {
        if (!isset($_SESSION['partial_login'])) {
            throw new Exception('No partial login session found.');
        }
        
        $partialLogin = $_SESSION['partial_login'];
        
        // Check if partial login has expired
        if (time() > $partialLogin['expires']) {
            unset($_SESSION['partial_login']);
            throw new Exception('2FA verification expired. Please login again.');
        }
        
        $user = $this->db->findById('users', $partialLogin['user_id']);
        if (!$user) {
            unset($_SESSION['partial_login']);
            throw new Exception('User not found.');
        }
        
        if (empty($user['twofa_secret'])) {
            unset($_SESSION['partial_login']);
            throw new Exception('2FA is not enabled for this user.');
        }
        
        // Verify TOTP token
        $totp = new TOTP();
        if (!$totp->verify($user['twofa_secret'], $token)) {
            throw new Exception('Invalid 2FA token.');
        }
        
        // Clear partial login and complete authentication
        unset($_SESSION['partial_login']);
        $this->completeLogin($user);
        
        return [
            'success' => true,
            'user' => $this->formatUserForResponse($user)
        ];
    }
    
    /**
     * Complete the login process
     */
    private function completeLogin(array $user): void {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Clear any rate limiting for this IP
        if (isset($_SESSION['rate_limits']['login_' . Security::getClientIp()])) {
            unset($_SESSION['rate_limits']['login_' . Security::getClientIp()]);
        }
    }
    
    /**
     * Logout user
     */
    public function logout(): void {
        Security::destroySession();
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Check session timeout
        if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
            $this->logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): ?array {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $user = $this->db->findById('users', $_SESSION['user_id']);
        return $user ? $this->formatUserForResponse($user) : null;
    }
    
    /**
     * Check if user has required role
     */
    public function hasRole(int $requiredRole): bool {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        return $_SESSION['user_role'] >= $requiredRole;
    }
    
    /**
     * Require authentication
     */
    public function requireAuth(): array {
        if (!$this->isAuthenticated()) {
            http_response_code(401);
            throw new Exception('Authentication required.');
        }
        
        return $this->getCurrentUser();
    }
    
    /**
     * Require specific role
     */
    public function requireRole(int $requiredRole): array {
        $user = $this->requireAuth();
        
        if (!$this->hasRole($requiredRole)) {
            http_response_code(403);
            throw new Exception('Insufficient permissions.');
        }
        
        return $user;
    }
    
    /**
     * Create new user (owner can create any role, admin can create up to editor)
     */
    public function createUser(string $email, string $password, int $role): array {
        $currentUser = $this->requireAuth();
        
        // Check permissions
        if ($currentUser['role'] < ROLE_ADMIN) {
            throw new Exception('Only admins can create users.');
        }
        
        // Owners can create any role, admins can only create up to editor level
        if ($currentUser['role'] < ROLE_OWNER && $role >= ROLE_ADMIN) {
            throw new Exception('Insufficient permissions to create user with this role.');
        }
        
        // Validate email
        if (!Security::validateEmail($email)) {
            throw new Exception('Invalid email address.');
        }
        
        // Check if email already exists
        if ($this->db->exists('users', 'email = :email', ['email' => $email])) {
            throw new Exception('Email already exists.');
        }
        
        // Validate password strength
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }
        
        // Hash password
        $passwordHash = Security::hashPassword($password);
        
        // Create user
        $userId = $this->db->insert('users', [
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role
        ]);
        
        return $this->formatUserForResponse($this->db->findById('users', $userId));
    }
    
    /**
     * Setup 2FA for user
     */
    public function setup2FA(): array {
        $user = $this->requireAuth();
        
        // Generate new secret
        $totp = new TOTP();
        $secret = $totp->generateSecret();
        
        // Store temporarily in session (not in database yet)
        $_SESSION['pending_2fa_secret'] = $secret;
        
        return [
            'secret' => $secret,
            'qr_url' => $totp->getQRCodeUrl($user['email'], $secret, 'Efed CMS')
        ];
    }
    
    /**
     * Enable 2FA after verifying setup
     */
    public function enable2FA(string $token): array {
        $user = $this->requireAuth();
        
        if (!isset($_SESSION['pending_2fa_secret'])) {
            throw new Exception('No pending 2FA setup found.');
        }
        
        $secret = $_SESSION['pending_2fa_secret'];
        
        // Verify the token
        $totp = new TOTP();
        if (!$totp->verify($secret, $token)) {
            throw new Exception('Invalid 2FA token.');
        }
        
        // Save secret to database
        $this->db->update('users', ['twofa_secret' => $secret], 'id = :id', ['id' => $user['id']]);
        
        // Clear pending secret
        unset($_SESSION['pending_2fa_secret']);
        
        return ['success' => true, 'message' => '2FA enabled successfully.'];
    }
    
    /**
     * Disable 2FA
     */
    public function disable2FA(string $token): array {
        $user = $this->requireAuth();
        
        if (empty($user['twofa_secret'])) {
            throw new Exception('2FA is not enabled.');
        }
        
        // Verify current token
        $totp = new TOTP();
        if (!$totp->verify($user['twofa_secret'], $token)) {
            throw new Exception('Invalid 2FA token.');
        }
        
        // Remove 2FA secret
        $this->db->update('users', ['twofa_secret' => null], 'id = :id', ['id' => $user['id']]);
        
        return ['success' => true, 'message' => '2FA disabled successfully.'];
    }
    
    /**
     * Seed initial owner user
     */
    public function seedOwner(string $email, string $password): array {
        // Check if any owner already exists
        if ($this->db->exists('users', 'role = :role', ['role' => ROLE_OWNER])) {
            throw new Exception('Owner user already exists.');
        }
        
        // Validate email
        if (!Security::validateEmail($email)) {
            throw new Exception('Invalid email address.');
        }
        
        // Validate password strength
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }
        
        // Hash password
        $passwordHash = Security::hashPassword($password);
        
        // Create owner user
        $userId = $this->db->insert('users', [
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => ROLE_OWNER
        ]);
        
        return $this->formatUserForResponse($this->db->findById('users', $userId));
    }
    
    /**
     * Format user data for API response (remove sensitive fields)
     */
    private function formatUserForResponse(array $user): array {
        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'role' => (int) $user['role'],
            'role_name' => ROLE_NAMES[$user['role']] ?? 'unknown',
            'has_2fa' => !empty($user['twofa_secret']),
            'created_at' => $user['created_at']
        ];
    }
    
    /**
     * Get role name from role number
     */
    public static function getRoleName(int $role): string {
        return ROLE_NAMES[$role] ?? 'unknown';
    }
    
    /**
     * Get role number from role name
     */
    public static function getRoleNumber(string $roleName): int {
        $roles = array_flip(ROLE_NAMES);
        return $roles[$roleName] ?? ROLE_VIEWER;
    }
}
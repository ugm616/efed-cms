<?php

class TOTP {
    
    const DIGITS = 6;
    const PERIOD = 30;
    const ALGORITHM = 'sha1';
    
    /**
     * Generate a random base32 secret
     */
    public function generateSecret(int $length = 32): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $secret;
    }
    
    /**
     * Generate TOTP token for given secret and time
     */
    public function generateToken(string $secret, ?int $time = null): string {
        if ($time === null) {
            $time = time();
        }
        
        $timeSlice = intval($time / self::PERIOD);
        $secretKey = $this->base32Decode($secret);
        
        // Pack time slice as 8-byte big-endian
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);
        
        // Generate HMAC
        $hash = hash_hmac(self::ALGORITHM, $timeBytes, $secretKey, true);
        
        // Dynamic truncation
        $offset = ord($hash[strlen($hash) - 1]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, self::DIGITS);
        
        return str_pad($code, self::DIGITS, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify TOTP token
     */
    public function verify(string $secret, string $token, int $window = 1): bool {
        $time = time();
        
        // Check current time slice and adjacent ones (to account for clock drift)
        for ($i = -$window; $i <= $window; $i++) {
            $testTime = $time + ($i * self::PERIOD);
            $expectedToken = $this->generateToken($secret, $testTime);
            
            if (hash_equals($expectedToken, $token)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get QR code URL for Google Authenticator
     */
    public function getQRCodeUrl(string $user, string $secret, string $issuer = 'Efed CMS'): string {
        $label = urlencode($issuer . ':' . $user);
        $issuer = urlencode($issuer);
        
        $otpauth = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
        
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth);
    }
    
    /**
     * Get manual entry key format
     */
    public function getManualEntryKey(string $secret): string {
        // Format as groups of 4 characters for easier manual entry
        return trim(chunk_split($secret, 4, ' '));
    }
    
    /**
     * Decode base32 string
     */
    private function base32Decode(string $input): string {
        if (empty($input)) {
            return '';
        }
        
        $input = strtoupper($input);
        $input = preg_replace('/[^A-Z2-7]/', '', $input);
        
        $map = [
            'A' => 0,  'B' => 1,  'C' => 2,  'D' => 3,
            'E' => 4,  'F' => 5,  'G' => 6,  'H' => 7,
            'I' => 8,  'J' => 9,  'K' => 10, 'L' => 11,
            'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
            'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
            'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
            'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27,
            '4' => 28, '5' => 29, '6' => 30, '7' => 31
        ];
        
        $output = '';
        $buffer = 0;
        $bufferLength = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            
            if (!isset($map[$char])) {
                continue;
            }
            
            $buffer = ($buffer << 5) | $map[$char];
            $bufferLength += 5;
            
            if ($bufferLength >= 8) {
                $output .= chr(($buffer >> ($bufferLength - 8)) & 0xFF);
                $bufferLength -= 8;
            }
        }
        
        return $output;
    }
    
    /**
     * Encode string to base32
     */
    private function base32Encode(string $input): string {
        if (empty($input)) {
            return '';
        }
        
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $buffer = 0;
        $bufferLength = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $buffer = ($buffer << 8) | ord($input[$i]);
            $bufferLength += 8;
            
            while ($bufferLength >= 5) {
                $index = ($buffer >> ($bufferLength - 5)) & 0x1F;
                $output .= $chars[$index];
                $bufferLength -= 5;
            }
        }
        
        if ($bufferLength > 0) {
            $index = ($buffer << (5 - $bufferLength)) & 0x1F;
            $output .= $chars[$index];
        }
        
        return $output;
    }
    
    /**
     * Generate backup codes
     */
    public function generateBackupCodes(int $count = 10): array {
        $codes = [];
        
        for ($i = 0; $i < $count; $i++) {
            // Generate 8-digit backup code
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= random_int(0, 9);
            }
            
            // Format as XXXX-XXXX
            $codes[] = substr($code, 0, 4) . '-' . substr($code, 4, 4);
        }
        
        return $codes;
    }
    
    /**
     * Validate backup code format
     */
    public function validateBackupCodeFormat(string $code): bool {
        return preg_match('/^\d{4}-\d{4}$/', $code) === 1;
    }
}
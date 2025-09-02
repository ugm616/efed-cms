# Efed CMS - Example API Usage

This document provides practical examples of how to use the Efed CMS API.

## Getting Started

### 1. Setup the Database

First, import the database schema:
```bash
mysql -u root -p
CREATE DATABASE efed_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE efed_cms;
SOURCE schema.sql;
```

### 2. Configure the Application

Edit `config.php` or set environment variables:
```bash
export DB_NAME=efed_cms
export DB_USER=root
export DB_PASS=your_password
export APP_KEY=your-32-character-secret-key-here
```

### 3. Create Initial Owner

Visit `/admin` and use the seed form to create the first owner account.

## Example API Calls

### Authentication

#### Get CSRF Token
```bash
curl -X GET "http://localhost/api/csrf"
```

Response:
```json
{
    "token": "abc123def456..."
}
```

#### Login
```bash
curl -X POST "http://localhost/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password123",
    "csrf_token": "abc123def456..."
  }'
```

Response:
```json
{
    "success": true,
    "user": {
        "id": 1,
        "email": "admin@example.com",
        "role": 5,
        "role_name": "owner",
        "has_2fa": false,
        "created_at": "2024-01-01 12:00:00"
    }
}
```

### Managing Wrestlers

#### Get All Wrestlers
```bash
curl -X GET "http://localhost/api/wrestlers?page=1&limit=10"
```

#### Search Wrestlers
```bash
curl -X GET "http://localhost/api/wrestlers?search=john&order_by=name&order_direction=ASC"
```

#### Get Specific Wrestler
```bash
curl -X GET "http://localhost/api/wrestlers/1"
```

#### Create Wrestler (requires authentication)
```bash
curl -X POST "http://localhost/api/wrestlers" \
  -H "Content-Type: application/json" \
  -b "cookies.txt" \
  -d '{
    "name": "John Cena",
    "slug": "john-cena",
    "active": true,
    "record_wins": 150,
    "record_losses": 45,
    "record_draws": 2,
    "elo": 1850,
    "points": 2500,
    "profile_img_url": "https://example.com/cena.jpg",
    "csrf_token": "abc123def456..."
  }'
```

#### Update Wrestler (requires editor role)
```bash
curl -X PUT "http://localhost/api/wrestlers/1" \
  -H "Content-Type: application/json" \
  -b "cookies.txt" \
  -d '{
    "record_wins": 151,
    "elo": 1860,
    "csrf_token": "abc123def456..."
  }'
```

### Managing Companies

#### Create Company
```bash
curl -X POST "http://localhost/api/companies" \
  -H "Content-Type: application/json" \
  -b "cookies.txt" \
  -d '{
    "name": "World Wrestling Entertainment",
    "slug": "wwe",
    "active": true,
    "logo_url": "https://example.com/wwe-logo.png",
    "banner_url": "https://example.com/wwe-banner.jpg",
    "links": "{\"website\": \"https://wwe.com\", \"twitter\": \"@WWE\"}",
    "csrf_token": "abc123def456..."
  }'
```

### Managing Events

#### Create Event
```bash
curl -X POST "http://localhost/api/events" \
  -H "Content-Type: application/json" \
  -b "cookies.txt" \
  -d '{
    "name": "WrestleMania 40",
    "slug": "wrestlemania-40",
    "company_id": 1,
    "date": "2024-04-07",
    "type": "pay-per-view",
    "venue": "Lincoln Financial Field",
    "attendance": 72543,
    "csrf_token": "abc123def456..."
  }'
```

### Managing Matches

#### Create Match
```bash
curl -X POST "http://localhost/api/matches" \
  -H "Content-Type: application/json" \
  -b "cookies.txt" \
  -d '{
    "slug": "cena-vs-reigns-wm40",
    "event_id": 1,
    "company_id": 1,
    "wrestler1_id": 1,
    "wrestler2_id": 2,
    "division_id": 1,
    "is_championship": true,
    "result_outcome": "win",
    "result_method": "pinfall",
    "result_round": 1,
    "judges": "[\"Mike Chioda\", \"Charles Robinson\"]",
    "csrf_token": "abc123def456..."
  }'
```

### Tag Management

#### Create Tag
```bash
curl -X POST "http://localhost/api/tags" \
  -H "Content-Type: application/json" \
  -b "cookies.txt" \
  -d '{
    "name": "Technical Wrestling",
    "slug": "technical-wrestling",
    "csrf_token": "abc123def456..."
  }'
```

#### Attach Tag to Entity
```bash
curl -X POST "http://localhost/api/tags/attach" \
  -H "Content-Type: application/json" \
  -b "cookies.txt" \
  -d '{
    "entity_type": "wrestlers",
    "entity_id": 1,
    "tag_id": 1,
    "csrf_token": "abc123def456..."
  }'
```

### Public Manifests

#### Get Wrestlers Manifest
```bash
curl -X GET "http://localhost/manifest/wrestlers.json"
```

Response:
```json
{
    "entity": "wrestlers",
    "count": 150,
    "generated_at": "2024-01-01T12:00:00Z",
    "data": [
        {
            "id": 1,
            "slug": "john-cena",
            "name": "John Cena",
            "active": true,
            "record_wins": 150,
            "record_losses": 45,
            "record_draws": 2,
            "elo": 1850,
            "points": 2500,
            "profile_img_url": "https://example.com/cena.jpg",
            "created_at": "2023-01-01 12:00:00"
        }
    ]
}
```

## PHP Example Client

Here's a simple PHP class to interact with the API:

```php
<?php

class EfedCMSClient {
    private $baseUrl;
    private $csrfToken;
    private $cookieJar;
    
    public function __construct($baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'efed_cms_cookies');
        $this->refreshCSRFToken();
    }
    
    private function refreshCSRFToken() {
        $response = $this->request('GET', '/api/csrf');
        $this->csrfToken = $response['token'];
    }
    
    public function login($email, $password) {
        return $this->request('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
            'csrf_token' => $this->csrfToken
        ]);
    }
    
    public function getWrestlers($page = 1, $limit = 20, $search = '') {
        $params = http_build_query([
            'page' => $page,
            'limit' => $limit,
            'search' => $search
        ]);
        
        return $this->request('GET', "/api/wrestlers?{$params}");
    }
    
    public function createWrestler($data) {
        $data['csrf_token'] = $this->csrfToken;
        return $this->request('POST', '/api/wrestlers', $data);
    }
    
    private function request($method, $path, $data = null) {
        $url = $this->baseUrl . $path;
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            throw new Exception($decoded['message'] ?? 'Request failed');
        }
        
        return $decoded;
    }
}

// Usage example
$client = new EfedCMSClient('http://localhost');

try {
    // Login
    $loginResult = $client->login('admin@example.com', 'password123');
    echo "Logged in successfully\n";
    
    // Get wrestlers
    $wrestlers = $client->getWrestlers(1, 10, 'john');
    echo "Found {$wrestlers['pagination']['total']} wrestlers\n";
    
    // Create wrestler
    $newWrestler = $client->createWrestler([
        'name' => 'The Rock',
        'active' => true,
        'record_wins' => 200,
        'record_losses' => 50,
        'elo' => 1900
    ]);
    echo "Created wrestler with ID: {$newWrestler['id']}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

## JavaScript Example (Browser)

```javascript
class EfedCMSClient {
    constructor(baseUrl) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.csrfToken = null;
    }
    
    async refreshCSRFToken() {
        const response = await fetch(`${this.baseUrl}/api/csrf`);
        const data = await response.json();
        this.csrfToken = data.token;
    }
    
    async login(email, password) {
        if (!this.csrfToken) await this.refreshCSRFToken();
        
        const response = await fetch(`${this.baseUrl}/api/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email,
                password,
                csrf_token: this.csrfToken
            }),
            credentials: 'same-origin'
        });
        
        return await response.json();
    }
    
    async getWrestlers(page = 1, limit = 20, search = '') {
        const params = new URLSearchParams({ page, limit, search });
        const response = await fetch(`${this.baseUrl}/api/wrestlers?${params}`);
        return await response.json();
    }
    
    async createWrestler(data) {
        if (!this.csrfToken) await this.refreshCSRFToken();
        
        const response = await fetch(`${this.baseUrl}/api/wrestlers`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                ...data,
                csrf_token: this.csrfToken
            }),
            credentials: 'same-origin'
        });
        
        return await response.json();
    }
}

// Usage
const client = new EfedCMSClient('http://localhost');

client.login('admin@example.com', 'password123')
    .then(() => console.log('Logged in successfully'))
    .then(() => client.getWrestlers(1, 10))
    .then(wrestlers => console.log(`Found ${wrestlers.pagination.total} wrestlers`))
    .catch(error => console.error('Error:', error));
```

## Testing with curl Scripts

Create a `test.sh` script for quick testing:

```bash
#!/bin/bash

BASE_URL="http://localhost"
CSRF_TOKEN=""
COOKIE_JAR="cookies.txt"

# Get CSRF token
get_csrf() {
    CSRF_TOKEN=$(curl -s "${BASE_URL}/api/csrf" | jq -r '.token')
    echo "CSRF Token: $CSRF_TOKEN"
}

# Login
login() {
    curl -X POST "${BASE_URL}/api/auth/login" \
        -H "Content-Type: application/json" \
        -c "$COOKIE_JAR" \
        -d "{
            \"email\": \"admin@example.com\",
            \"password\": \"password123\",
            \"csrf_token\": \"$CSRF_TOKEN\"
        }"
}

# Test wrestler creation
create_wrestler() {
    curl -X POST "${BASE_URL}/api/wrestlers" \
        -H "Content-Type: application/json" \
        -b "$COOKIE_JAR" \
        -d "{
            \"name\": \"Test Wrestler\",
            \"active\": true,
            \"record_wins\": 10,
            \"record_losses\": 5,
            \"elo\": 1400,
            \"csrf_token\": \"$CSRF_TOKEN\"
        }"
}

# Run tests
get_csrf
login
create_wrestler

# Cleanup
rm -f "$COOKIE_JAR"
```

Make it executable and run:
```bash
chmod +x test.sh
./test.sh
```

## Common Issues and Solutions

### 1. CSRF Token Issues
Always get a fresh CSRF token before making authenticated requests.

### 2. Cookie Handling
Use `-b` and `-c` flags with curl to handle session cookies properly.

### 3. JSON Formatting
Ensure JSON is properly formatted and Content-Type header is set.

### 4. Permission Errors
Check user roles - some operations require specific permission levels.

### 5. Database Connection
Verify database credentials and that the MySQL service is running.

For more information, see the complete setup guide in `SETUP.md`.
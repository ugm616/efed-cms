<?php

// Load configuration
require_once __DIR__ . '/config.php';

// Start secure session
Security::startSecureSession();

// Set security headers
Security::setSecurityHeaders();

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');

// Enable CORS for admin interface
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uri, '/'));

try {
    // Initialize database and auth
    $db = DB::getInstance();
    $auth = new Auth();
    
    // Router logic
    if ($segments[0] === 'api') {
        handleApiRequest($segments, $method, $auth, $db);
    } elseif ($segments[0] === 'manifest') {
        handleManifestRequest($segments, $method, $db);
    } else {
        throw new Exception('Not found', 404);
    }
    
} catch (Exception $e) {
    $statusCode = is_numeric($e->getMessage()) ? (int) $e->getMessage() : 
                  (method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500);
    
    if ($statusCode < 100 || $statusCode > 599) {
        $statusCode = 500;
    }
    
    http_response_code($statusCode);
    
    $response = [
        'error' => true,
        'message' => $statusCode === 500 && !APP_DEBUG ? 'Internal server error' : $e->getMessage()
    ];
    
    if (APP_DEBUG && $statusCode === 500) {
        $response['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    echo Security::safeJsonEncode($response);
}

/**
 * Handle API requests
 */
function handleApiRequest(array $segments, string $method, Auth $auth, DB $db): void {
    if (count($segments) < 2) {
        throw new Exception('Invalid API endpoint', 400);
    }
    
    $endpoint = $segments[1];
    
    switch ($endpoint) {
        case 'auth':
            handleAuthEndpoint($segments, $method, $auth, $db);
            break;
            
        case 'csrf':
            if ($method === 'GET') {
                echo Security::safeJsonEncode(['token' => Security::generateCSRFToken()]);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case 'wrestlers':
        case 'companies':
        case 'divisions':
        case 'events':
        case 'matches':
            handleEntityEndpoint($endpoint, $segments, $method, $auth, $db);
            break;
            
        case 'tags':
            handleTagsEndpoint($segments, $method, $auth, $db);
            break;
            
        case 'build':
            handleBuildEndpoint($method, $auth, $db);
            break;
            
        default:
            throw new Exception('Endpoint not found', 404);
    }
}

/**
 * Handle authentication endpoints
 */
function handleAuthEndpoint(array $segments, string $method, Auth $auth, DB $db): void {
    if (count($segments) < 3) {
        throw new Exception('Invalid auth endpoint', 400);
    }
    
    $action = $segments[2];
    
    switch ($action) {
        case 'seed-owner':
            if ($method === 'POST') {
                $data = getJsonInput();
                validateCSRF($data);
                
                $user = $auth->seedOwner($data['email'], $data['password']);
                echo Security::safeJsonEncode(['success' => true, 'user' => $user]);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case 'login':
            if ($method === 'POST') {
                $data = getJsonInput();
                validateCSRF($data);
                
                $result = $auth->login($data['email'], $data['password']);
                echo Security::safeJsonEncode($result);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case 'logout':
            if ($method === 'POST') {
                $data = getJsonInput();
                validateCSRF($data);
                
                $auth->logout();
                echo Security::safeJsonEncode(['success' => true]);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case 'user':
            if ($method === 'GET') {
                $user = $auth->getCurrentUser();
                if ($user) {
                    echo Security::safeJsonEncode($user);
                } else {
                    http_response_code(401);
                    echo Security::safeJsonEncode(['error' => true, 'message' => 'Not authenticated']);
                }
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case '2fa':
            handle2FAEndpoint($segments, $method, $auth);
            break;
            
        default:
            throw new Exception('Auth endpoint not found', 404);
    }
}

/**
 * Handle 2FA endpoints
 */
function handle2FAEndpoint(array $segments, string $method, Auth $auth): void {
    if (count($segments) < 4) {
        throw new Exception('Invalid 2FA endpoint', 400);
    }
    
    $action = $segments[3];
    
    switch ($action) {
        case 'setup':
            if ($method === 'POST') {
                $data = getJsonInput();
                validateCSRF($data);
                
                $result = $auth->setup2FA();
                echo Security::safeJsonEncode($result);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case 'verify':
            if ($method === 'POST') {
                $data = getJsonInput();
                validateCSRF($data);
                
                $result = $auth->verify2FA($data['token']);
                echo Security::safeJsonEncode($result);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case 'enable':
            if ($method === 'POST') {
                $data = getJsonInput();
                validateCSRF($data);
                
                $result = $auth->enable2FA($data['token']);
                echo Security::safeJsonEncode($result);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case 'disable':
            if ($method === 'POST') {
                $data = getJsonInput();
                validateCSRF($data);
                
                $result = $auth->disable2FA($data['token']);
                echo Security::safeJsonEncode($result);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        default:
            throw new Exception('2FA endpoint not found', 404);
    }
}

/**
 * Handle entity CRUD endpoints
 */
function handleEntityEndpoint(string $entity, array $segments, string $method, Auth $auth, DB $db): void {
    $entitySingular = rtrim($entity, 's');
    $id = isset($segments[2]) ? (int) $segments[2] : null;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single entity
                $result = $db->getEntityWithTags($entity, $id);
                if (!$result) {
                    throw new Exception('Entity not found', 404);
                }
                echo Security::safeJsonEncode($result);
            } else {
                // Get list with pagination
                $params = Validators::validatePagination($_GET);
                $params['search_fields'] = ['name', 'slug'];
                
                $result = $db->getListWithPagination($entity, $params);
                echo Security::safeJsonEncode($result);
            }
            break;
            
        case 'POST':
            // Create entity - requires contributor role
            $auth->requireRole(ROLE_CONTRIBUTOR);
            
            $data = getJsonInput();
            validateCSRF($data);
            
            $validation = validateEntityData($entity, $data, false);
            if (!empty($validation['errors'])) {
                http_response_code(422);
                echo Security::safeJsonEncode(['error' => true, 'errors' => $validation['errors']]);
                return;
            }
            
            // Check foreign key constraints
            $fkErrors = validateEntityForeignKeys($entity, $validation['data'], $db);
            if (!empty($fkErrors)) {
                http_response_code(422);
                echo Security::safeJsonEncode(['error' => true, 'errors' => $fkErrors]);
                return;
            }
            
            // Check slug uniqueness
            if (isset($validation['data']['slug']) && 
                $db->slugExists($entity, $validation['data']['slug'])) {
                http_response_code(422);
                echo Security::safeJsonEncode(['error' => true, 'errors' => ['slug' => 'Slug already exists']]);
                return;
            }
            
            $newId = $db->insert($entity, $validation['data']);
            $result = $db->getEntityWithTags($entity, $newId);
            
            // Trigger manifest rebuild
            rebuildManifests($db);
            
            http_response_code(201);
            echo Security::safeJsonEncode($result);
            break;
            
        case 'PUT':
            // Update entity - requires editor role
            $auth->requireRole(ROLE_EDITOR);
            
            if (!$id) {
                throw new Exception('Entity ID is required', 400);
            }
            
            $existing = $db->findById($entity, $id);
            if (!$existing) {
                throw new Exception('Entity not found', 404);
            }
            
            $data = getJsonInput();
            validateCSRF($data);
            
            $validation = validateEntityData($entity, $data, true);
            if (!empty($validation['errors'])) {
                http_response_code(422);
                echo Security::safeJsonEncode(['error' => true, 'errors' => $validation['errors']]);
                return;
            }
            
            // Check foreign key constraints
            $fkErrors = validateEntityForeignKeys($entity, $validation['data'], $db);
            if (!empty($fkErrors)) {
                http_response_code(422);
                echo Security::safeJsonEncode(['error' => true, 'errors' => $fkErrors]);
                return;
            }
            
            // Check slug uniqueness (excluding current entity)
            if (isset($validation['data']['slug']) && 
                $db->slugExists($entity, $validation['data']['slug'], $id)) {
                http_response_code(422);
                echo Security::safeJsonEncode(['error' => true, 'errors' => ['slug' => 'Slug already exists']]);
                return;
            }
            
            $db->update($entity, $validation['data'], 'id = :id', ['id' => $id]);
            $result = $db->getEntityWithTags($entity, $id);
            
            // Trigger manifest rebuild
            rebuildManifests($db);
            
            echo Security::safeJsonEncode($result);
            break;
            
        case 'DELETE':
            // Delete entity - requires admin role
            $auth->requireRole(ROLE_ADMIN);
            
            if (!$id) {
                throw new Exception('Entity ID is required', 400);
            }
            
            $existing = $db->findById($entity, $id);
            if (!$existing) {
                throw new Exception('Entity not found', 404);
            }
            
            $data = getJsonInput();
            validateCSRF($data);
            
            $deleted = $db->delete($entity, 'id = :id', ['id' => $id]);
            
            if ($deleted > 0) {
                // Trigger manifest rebuild
                rebuildManifests($db);
                
                echo Security::safeJsonEncode(['success' => true, 'deleted' => $deleted]);
            } else {
                throw new Exception('Failed to delete entity', 500);
            }
            break;
            
        default:
            throw new Exception('Method not allowed', 405);
    }
}

/**
 * Handle tags endpoints
 */
function handleTagsEndpoint(array $segments, string $method, Auth $auth, DB $db): void {
    switch ($method) {
        case 'GET':
            // Get all tags
            $params = Validators::validatePagination($_GET);
            $params['search_fields'] = ['name', 'slug'];
            
            $result = $db->getListWithPagination('tags', $params);
            echo Security::safeJsonEncode($result);
            break;
            
        case 'POST':
            if (isset($segments[2])) {
                $action = $segments[2];
                
                switch ($action) {
                    case 'attach':
                        $auth->requireRole(ROLE_EDITOR);
                        
                        $data = getJsonInput();
                        validateCSRF($data);
                        
                        $result = $db->attachTag($data['entity_type'], $data['entity_id'], $data['tag_id']);
                        echo Security::safeJsonEncode(['success' => $result]);
                        break;
                        
                    case 'detach':
                        $auth->requireRole(ROLE_EDITOR);
                        
                        $data = getJsonInput();
                        validateCSRF($data);
                        
                        $result = $db->detachTag($data['entity_type'], $data['entity_id'], $data['tag_id']);
                        echo Security::safeJsonEncode(['success' => $result]);
                        break;
                        
                    default:
                        throw new Exception('Invalid tag action', 400);
                }
            } else {
                // Create tag - requires contributor role
                $auth->requireRole(ROLE_CONTRIBUTOR);
                
                $data = getJsonInput();
                validateCSRF($data);
                
                $validation = Validators::validateTag($data, false);
                if (!empty($validation['errors'])) {
                    http_response_code(422);
                    echo Security::safeJsonEncode(['error' => true, 'errors' => $validation['errors']]);
                    return;
                }
                
                // Check slug uniqueness
                if ($db->slugExists('tags', $validation['data']['slug'])) {
                    http_response_code(422);
                    echo Security::safeJsonEncode(['error' => true, 'errors' => ['slug' => 'Slug already exists']]);
                    return;
                }
                
                $newId = $db->insert('tags', $validation['data']);
                $result = $db->findById('tags', $newId);
                
                http_response_code(201);
                echo Security::safeJsonEncode($result);
            }
            break;
            
        default:
            throw new Exception('Method not allowed', 405);
    }
}

/**
 * Handle build endpoint
 */
function handleBuildEndpoint(string $method, Auth $auth, DB $db): void {
    if ($method === 'POST') {
        $auth->requireRole(ROLE_EDITOR);
        
        $data = getJsonInput();
        validateCSRF($data);
        
        rebuildManifests($db);
        
        echo Security::safeJsonEncode(['success' => true, 'message' => 'Manifests rebuilt']);
    } else {
        throw new Exception('Method not allowed', 405);
    }
}

/**
 * Handle public manifest requests
 */
function handleManifestRequest(array $segments, string $method, DB $db): void {
    if ($method !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }
    
    if (count($segments) < 2) {
        throw new Exception('Invalid manifest request', 400);
    }
    
    $manifest = $segments[1];
    $validManifests = ['wrestlers.json', 'companies.json', 'divisions.json', 'events.json', 'matches.json'];
    
    if (!in_array($manifest, $validManifests)) {
        throw new Exception('Manifest not found', 404);
    }
    
    $entity = str_replace('.json', '', $manifest);
    $data = buildManifestData($entity, $db);
    
    // Set cache headers
    Security::setCacheHeaders(Security::safeJsonEncode($data));
    
    echo Security::safeJsonEncode($data);
}

/**
 * Get JSON input from request body
 */
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return [];
    }
    
    try {
        return Security::safeJsonDecode($input, true);
    } catch (Exception $e) {
        throw new Exception('Invalid JSON input', 400);
    }
}

/**
 * Validate CSRF token
 */
function validateCSRF(array $data): void {
    if (!isset($data['csrf_token']) || !Security::verifyCSRFToken($data['csrf_token'])) {
        throw new Exception('Invalid CSRF token', 403);
    }
}

/**
 * Validate entity data using appropriate validator
 */
function validateEntityData(string $entity, array $data, bool $isUpdate): array {
    switch ($entity) {
        case 'wrestlers':
            return Validators::validateWrestler($data, $isUpdate);
        case 'companies':
            return Validators::validateCompany($data, $isUpdate);
        case 'divisions':
            return Validators::validateDivision($data, $isUpdate);
        case 'events':
            return Validators::validateEvent($data, $isUpdate);
        case 'matches':
            return Validators::validateMatch($data, $isUpdate);
        default:
            throw new Exception('Unknown entity type', 400);
    }
}

/**
 * Validate foreign key constraints for entity
 */
function validateEntityForeignKeys(string $entity, array $data, DB $db): array {
    $references = [];
    
    switch ($entity) {
        case 'events':
            $references['company_id'] = 'companies';
            break;
        case 'matches':
            $references = [
                'event_id' => 'events',
                'company_id' => 'companies',
                'wrestler1_id' => 'wrestlers',
                'wrestler2_id' => 'wrestlers'
            ];
            if (isset($data['division_id']) && !empty($data['division_id'])) {
                $references['division_id'] = 'divisions';
            }
            break;
    }
    
    return Validators::validateForeignKeys($data, $references);
}

/**
 * Build manifest data for public consumption
 */
function buildManifestData(string $entity, DB $db): array {
    $items = $db->fetchAll("SELECT * FROM {$entity} WHERE active = 1 ORDER BY created_at DESC");
    
    // Format data for public consumption
    foreach ($items as &$item) {
        // Convert numeric fields
        $numericFields = ['id', 'record_wins', 'record_losses', 'record_draws', 'elo', 'points', 'attendance'];
        foreach ($numericFields as $field) {
            if (isset($item[$field])) {
                $item[$field] = (int) $item[$field];
            }
        }
        
        // Convert boolean fields
        if (isset($item['active'])) {
            $item['active'] = (bool) $item['active'];
        }
        if (isset($item['is_championship'])) {
            $item['is_championship'] = (bool) $item['is_championship'];
        }
        
        // Decode JSON fields
        $jsonFields = ['links', 'eligibility', 'judges'];
        foreach ($jsonFields as $field) {
            if (isset($item[$field]) && !empty($item[$field])) {
                $item[$field] = json_decode($item[$field], true);
            }
        }
    }
    
    return [
        'entity' => $entity,
        'count' => count($items),
        'generated_at' => date('c'),
        'data' => $items
    ];
}

/**
 * Rebuild all public manifests
 */
function rebuildManifests(DB $db): void {
    $entities = ['wrestlers', 'companies', 'divisions', 'events', 'matches'];
    $manifestDir = ROOT_PATH . '/manifests';
    
    // Create manifests directory if it doesn't exist
    if (!is_dir($manifestDir)) {
        mkdir($manifestDir, 0755, true);
    }
    
    foreach ($entities as $entity) {
        $data = buildManifestData($entity, $db);
        $json = Security::safeJsonEncode($data, JSON_PRETTY_PRINT);
        file_put_contents($manifestDir . "/{$entity}.json", $json);
    }
}
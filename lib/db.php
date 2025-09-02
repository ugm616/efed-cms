<?php

class DB {
    private static $instance = null;
    private $pdo = null;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance(): DB {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect(): void {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];
        
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                throw new Exception('Database connection failed: ' . $e->getMessage());
            } else {
                throw new Exception('Database connection failed');
            }
        }
    }
    
    public function getPDO(): PDO {
        return $this->pdo;
    }
    
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetch(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert(string $table, array $data): int {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $fieldList = implode(', ', $fields);
        
        $sql = "INSERT INTO {$table} ({$fieldList}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return (int) $this->pdo->lastInsertId();
    }
    
    public function update(string $table, array $data, string $whereClause, array $whereParams = []): int {
        $setParts = [];
        foreach ($data as $field => $value) {
            $setParts[] = "{$field} = :{$field}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function delete(string $table, string $whereClause, array $whereParams = []): int {
        $sql = "DELETE FROM {$table} WHERE {$whereClause}";
        $stmt = $this->query($sql, $whereParams);
        return $stmt->rowCount();
    }
    
    public function exists(string $table, string $whereClause, array $whereParams = []): bool {
        $sql = "SELECT 1 FROM {$table} WHERE {$whereClause} LIMIT 1";
        $result = $this->fetch($sql, $whereParams);
        return $result !== null;
    }
    
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }
    
    public function commit(): bool {
        return $this->pdo->commit();
    }
    
    public function rollback(): bool {
        return $this->pdo->rollback();
    }
    
    public function inTransaction(): bool {
        return $this->pdo->inTransaction();
    }
    
    // Entity-specific helper methods
    
    public function findBySlug(string $table, string $slug): ?array {
        return $this->fetch("SELECT * FROM {$table} WHERE slug = :slug", ['slug' => $slug]);
    }
    
    public function findById(string $table, int $id): ?array {
        return $this->fetch("SELECT * FROM {$table} WHERE id = :id", ['id' => $id]);
    }
    
    public function slugExists(string $table, string $slug, ?int $excludeId = null): bool {
        $sql = "SELECT 1 FROM {$table} WHERE slug = :slug";
        $params = ['slug' => $slug];
        
        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        
        return $this->exists($table, "slug = :slug" . ($excludeId ? " AND id != :id" : ""), $params);
    }
    
    public function getEntityWithTags(string $table, int $id): ?array {
        $entity = $this->findById($table, $id);
        if (!$entity) {
            return null;
        }
        
        // Get tags for this entity
        $entitySingular = rtrim($table, 's'); // Simple pluralization removal
        $tagTable = $entitySingular . '_tags';
        
        if ($this->tableExists($tagTable)) {
            $tags = $this->fetchAll(
                "SELECT t.* FROM tags t 
                 INNER JOIN {$tagTable} et ON t.id = et.tag_id 
                 WHERE et.{$entitySingular}_id = :id
                 ORDER BY t.name",
                ['id' => $id]
            );
            $entity['tags'] = $tags;
        }
        
        return $entity;
    }
    
    public function tableExists(string $table): bool {
        $sql = "SELECT 1 FROM information_schema.tables 
                WHERE table_schema = :database AND table_name = :table";
        $params = ['database' => DB_NAME, 'table' => $table];
        
        return $this->fetch($sql, $params) !== null;
    }
    
    public function attachTag(string $entityType, int $entityId, int $tagId): bool {
        $table = $entityType . '_tags';
        $entityColumn = rtrim($entityType, 's') . '_id';
        
        try {
            $this->insert($table, [
                $entityColumn => $entityId,
                'tag_id' => $tagId
            ]);
            return true;
        } catch (PDOException $e) {
            // Ignore duplicate key errors
            if ($e->getCode() === '23000') {
                return true;
            }
            throw $e;
        }
    }
    
    public function detachTag(string $entityType, int $entityId, int $tagId): bool {
        $table = $entityType . '_tags';
        $entityColumn = rtrim($entityType, 's') . '_id';
        
        $deleted = $this->delete($table, "{$entityColumn} = :entity_id AND tag_id = :tag_id", [
            'entity_id' => $entityId,
            'tag_id' => $tagId
        ]);
        
        return $deleted > 0;
    }
    
    public function getListWithPagination(string $table, array $options = []): array {
        $page = max(1, $options['page'] ?? 1);
        $limit = min(MAX_PAGE_SIZE, max(1, $options['limit'] ?? DEFAULT_PAGE_SIZE));
        $offset = ($page - 1) * $limit;
        
        $where = '';
        $params = [];
        $orderBy = 'ORDER BY created_at DESC';
        
        // Search functionality
        if (!empty($options['search'])) {
            $searchFields = $options['search_fields'] ?? ['name'];
            $searchConditions = [];
            foreach ($searchFields as $field) {
                $searchConditions[] = "{$field} LIKE :search";
            }
            $where = 'WHERE (' . implode(' OR ', $searchConditions) . ')';
            $params['search'] = '%' . $options['search'] . '%';
        }
        
        // Custom ordering
        if (!empty($options['order_by'])) {
            $direction = strtoupper($options['order_direction'] ?? 'ASC');
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                $direction = 'ASC';
            }
            $orderBy = "ORDER BY {$options['order_by']} {$direction}";
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$table} {$where}";
        $totalResult = $this->fetch($countSql, $params);
        $total = (int) $totalResult['total'];
        
        // Get paginated results
        $sql = "SELECT * FROM {$table} {$where} {$orderBy} LIMIT {$limit} OFFSET {$offset}";
        $items = $this->fetchAll($sql, $params);
        
        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
}
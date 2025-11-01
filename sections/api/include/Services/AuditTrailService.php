<?php
namespace App\Services;

use PDO;

class AuditTrailService {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log audit event
     */
    public function logEvent(
        string $eventType,
        string $actorType,
        ?string $actorId,
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        array $details = [],
        array $metadata = [],
        string $severity = 'info'
    ): void {
        // Add request context to metadata
        $metadata = array_merge([
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'timestamp' => date('c'),
        ], $metadata);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_events (
                event_type, actor_type, actor_id, target_type, target_id,
                action, details, metadata, severity
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $eventType,
            $actorType,
            $actorId,
            $targetType,
            $targetId,
            $action,
            json_encode($details),
            json_encode($metadata),
            $severity
        ]);
    }
    
    /**
     * Query audit events
     */
    public function query(array $filters = [], int $limit = 100, int $offset = 0): array {
        $where = [];
        $params = [];
        
        if (isset($filters['event_type'])) {
            $where[] = "event_type = ?";
            $params[] = $filters['event_type'];
        }
        
        if (isset($filters['actor_id'])) {
            $where[] = "actor_id = ?";
            $params[] = $filters['actor_id'];
        }
        
        if (isset($filters['severity'])) {
            $where[] = "severity = ?";
            $params[] = $filters['severity'];
        }
        
        if (isset($filters['from_date'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['from_date'];
        }
        
        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM audit_events
            $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute(array_merge($params, [$limit, $offset]));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Log authentication event
     */
    public function logAuth(string $action, ?int $userId, bool $success, array $details = []): void {
        $this->logEvent(
            'authentication',
            $userId ? 'user' : 'system',
            $userId ? (string)$userId : null,
            $action,
            null,
            null,
            array_merge($details, ['success' => $success]),
            [],
            $success ? 'info' : 'warning'
        );
    }
    
    /**
     * Log data modification
     */
    public function logDataChange(string $table, string $action, $id, array $changes = []): void {
        $this->logEvent(
            'data_modification',
            'user',
            (string)($_SESSION['user_id'] ?? 'system'),
            $action,
            $table,
            (string)$id,
            ['changes' => $changes],
            [],
            'info'
        );
    }
}

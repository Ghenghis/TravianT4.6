<?php
namespace App\Security;

use PDO;

class DatabaseSecurity {
    /**
     * Execute safe SELECT query with prepared statements
     */
    public static function safeSelect(PDO $pdo, string $query, array $params = []): array {
        if (!preg_match('/^\s*SELECT/i', $query)) {
            throw new \InvalidArgumentException('Query must start with SELECT');
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Execute safe INSERT query with prepared statements
     */
    public static function safeInsert(PDO $pdo, string $query, array $params = []): int {
        if (!preg_match('/^\s*INSERT/i', $query)) {
            throw new \InvalidArgumentException('Query must start with INSERT');
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return (int)$pdo->lastInsertId();
    }
    
    /**
     * Execute safe UPDATE query with prepared statements
     */
    public static function safeUpdate(PDO $pdo, string $query, array $params = []): int {
        if (!preg_match('/^\s*UPDATE/i', $query)) {
            throw new \InvalidArgumentException('Query must start with UPDATE');
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * Execute safe DELETE query with prepared statements
     */
    public static function safeDelete(PDO $pdo, string $query, array $params = []): int {
        if (!preg_match('/^\s*DELETE/i', $query)) {
            throw new \InvalidArgumentException('Query must start with DELETE');
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * Validate identifier (table/column name)
     * Prevents SQL injection in dynamic identifiers
     */
    public static function validateIdentifier(string $identifier): string {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid identifier: ' . $identifier);
        }
        return $identifier;
    }
}

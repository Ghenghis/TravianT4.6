#!/usr/bin/env php
<?php
/**
 * Cleanup Orphaned NPC Data
 * 
 * This script finds and cleans up orphaned NPC data from crashes.
 * 
 * WHAT IT DOES:
 * 1. Finds stuck NPC creation records (> 1 hour old)
 * 2. For 'mysql_committed' records: Cleans up orphaned MySQL data
 * 3. For 'pending' records: Marks as failed (nothing to clean)
 * 
 * CRON SCHEDULE:
 * Run every hour: 0 * * * * php /path/to/scripts/cleanup-orphaned-npcs.php
 * 
 * SAFETY:
 * - Idempotent: Can run multiple times safely
 * - Limits processing to 100 records per run
 * - Logs all actions for audit trail
 */

require_once __DIR__ . '/../sections/api/include/bootstrap.php';

use Database\DB;
use Database\DatabaseBridge;

echo "[" . date('Y-m-d H:i:s') . "] Starting orphaned NPC cleanup...\n";

try {
    $db = DB::getInstance();
    
    // Fix 2: Handle DatabaseBridge connection failures gracefully
    try {
        $bridge = DatabaseBridge::getInstance();
    } catch (\Exception $e) {
        echo "✗ DatabaseBridge initialization failed: " . $e->getMessage() . "\n";
        exit(1);
    }

    // Fix 5: Update query to exclude 'blocked' records
    $stuckRecords = $db->query("
        SELECT * FROM pending_npc_creations 
        WHERE (
            -- Records in transition states
            status IN ('mysql_committing', 'mysql_committed', 'postgres_committing', 'postgres_committed')
            OR 
            -- Edge case: non-NULL mysql_user_id regardless of status
            -- This catches cases where status write failed but MySQL data exists
            mysql_user_id IS NOT NULL
        )
        AND status NOT IN ('completed', 'failed', 'blocked')  -- Don't reprocess blocked records
        AND created_at < NOW() - INTERVAL '1 hour'
        ORDER BY created_at ASC
        LIMIT 100
    ")->fetchAll();

    echo "Found " . count($stuckRecords) . " stuck NPC creation records\n\n";

    if (count($stuckRecords) === 0) {
        echo "No orphaned records found. System is healthy!\n";
        exit(0);
    }

    $cleanedCount = 0;
    $failedCount = 0;

    foreach ($stuckRecords as $record) {
        $recordId = $record['id'];
        $status = $record['status'];
        $worldId = $record['world_id'];
        $age = round((time() - strtotime($record['created_at'])) / 3600, 2);

        echo "Processing record #{$recordId} (status: {$status}, age: {$age} hours)\n";

        $postgresExists = false;
        if ($record['postgres_player_id']) {
            $checkStmt = $db->prepare("SELECT 1 FROM players WHERE id = ? LIMIT 1");
            $checkStmt->execute([$record['postgres_player_id']]);
            $postgresExists = (bool)$checkStmt->fetchColumn();
        }

        $mysqlExists = ($record['mysql_user_id'] !== null);

        if ($postgresExists && $mysqlExists) {
            echo "  ! Found complete spawn with incomplete status marker\n";
            echo "    PostgreSQL player ID: {$record['postgres_player_id']}\n";
            echo "    MySQL user ID: {$record['mysql_user_id']}\n";
            
            try {
                // Update exactly as completePendingRecord() does for consistency
                $db->prepare("
                    UPDATE pending_npc_creations 
                    SET status = 'completed', 
                        postgres_player_id = ?,
                        completed_at = NOW() 
                    WHERE id = ?
                ")->execute([$record['postgres_player_id'], $recordId]);
                
                echo "  ✓ Marked as completed (both databases consistent)\n";
                $cleanedCount++;
            } catch (\Exception $e) {
                echo "  ✗ Failed to update status: " . $e->getMessage() . "\n";
                $failedCount++;
            }

        } elseif ($postgresExists && !$mysqlExists) {
            echo "  ! Found orphaned PostgreSQL data (MySQL never committed)\n";
            echo "    PostgreSQL player ID: {$record['postgres_player_id']}\n";
            
            try {
                // CRITICAL: Delete in FK-safe order (children first, then parent)
                // FK Constraints: ai_configs.npc_player_id → players.id (CASCADE)
                //                 world_npc_spawns.npc_player_id → players.id (CASCADE)
                
                // 1. Delete ai_configs (child table)
                $db->prepare("DELETE FROM ai_configs WHERE npc_player_id = ?")->execute([$record['postgres_player_id']]);
                echo "    ✓ Deleted AI config\n";
                
                // 2. Delete world_npc_spawns (child table)
                $db->prepare("DELETE FROM world_npc_spawns WHERE npc_player_id = ?")->execute([$record['postgres_player_id']]);
                echo "    ✓ Deleted spawn record\n";
                
                // 3. Now safe to delete players (parent table)
                $db->prepare("DELETE FROM players WHERE id = ?")->execute([$record['postgres_player_id']]);
                echo "    ✓ Deleted player record\n";
                
                // 4. Update pending record - NULL out postgres_player_id to prevent reprocessing
                $db->prepare("
                    UPDATE pending_npc_creations 
                    SET status = 'failed', 
                        error_message = 'Cleaned orphaned PostgreSQL data (MySQL never committed)', 
                        postgres_player_id = NULL,
                        completed_at = NOW() 
                    WHERE id = ?
                ")->execute([$recordId]);
                
                echo "  ✓ Successfully cleaned up orphaned PostgreSQL data (FK-safe order)\n";
                $cleanedCount++;
            } catch (\Exception $e) {
                echo "  ✗ PostgreSQL cleanup failed: " . $e->getMessage() . "\n";
                $db->prepare("
                    UPDATE pending_npc_creations 
                    SET error_message = ? 
                    WHERE id = ?
                ")->execute([
                    'PostgreSQL cleanup attempt failed: ' . substr($e->getMessage(), 0, 900),
                    $recordId
                ]);
                $failedCount++;
            }

        } elseif (!$postgresExists && $mysqlExists) {
            echo "  ! Found orphaned MySQL data (PostgreSQL never committed)\n";
            echo "    MySQL user ID: {$record['mysql_user_id']}\n";
            
            $mysqlConn = null;
            try {
                $worldStmt = $db->prepare("
                    SELECT configfilelocation 
                    FROM gameservers 
                    WHERE worldid = ? AND active = 1
                ");
                $worldStmt->execute([$worldId]);
                $worldInfo = $worldStmt->fetch();

                if (!$worldInfo) {
                    echo "  ⚠ World {$worldId} not found or inactive, marking as failed\n";
                    // NULL out MySQL IDs to prevent reprocessing
                    $db->prepare("
                        UPDATE pending_npc_creations 
                        SET status = 'failed', 
                            error_message = 'World not found or inactive', 
                            mysql_user_id = NULL,
                            mysql_village_id = NULL,
                            coordinates_x = NULL,
                            coordinates_y = NULL,
                            completed_at = NOW() 
                        WHERE id = ?
                    ")->execute([$recordId]);
                    $failedCount++;
                    continue;
                }

                $mysqlConn = $bridge->getMySQLConnection($worldInfo['configfilelocation']);

                // Fix 1: Start MySQL transaction for atomic cleanup
                $mysqlConn->beginTransaction();

                // CRITICAL: Delete in FK-safe order (children first, then parent)
                // FK Constraints: fdata.kid → vdata.kid
                //                 vdata.owner → users.id
                
                if ($record['mysql_village_id']) {
                    // 1. Delete fdata (child of vdata)
                    $mysqlConn->prepare("DELETE FROM fdata WHERE kid = ?")->execute([$record['mysql_village_id']]);
                    echo "    ✓ Deleted resource fields\n";

                    // 2. Delete vdata (child of users)
                    $mysqlConn->prepare("DELETE FROM vdata WHERE kid = ?")->execute([$record['mysql_village_id']]);
                    echo "    ✓ Deleted village\n";
                }

                // 3. Free map coordinates (no FK dependency)
                if ($record['coordinates_x'] !== null && $record['coordinates_y'] !== null) {
                    $mysqlConn->prepare("UPDATE wdata SET occupied = 0 WHERE x = ? AND y = ?")->execute([
                        $record['coordinates_x'], 
                        $record['coordinates_y']
                    ]);
                    echo "    ✓ Freed map coordinates ({$record['coordinates_x']}, {$record['coordinates_y']})\n";
                }

                // 4. Delete users (parent table)
                $mysqlConn->prepare("DELETE FROM users WHERE id = ?")->execute([$record['mysql_user_id']]);
                echo "    ✓ Deleted user account\n";

                // CRITICAL: Only commit if all deletions succeeded
                $mysqlConn->commit();
                echo "    ✓ MySQL transaction committed\n";

                // CRITICAL: NULL out MySQL IDs only after successful commit
                $db->prepare("
                    UPDATE pending_npc_creations 
                    SET status = 'failed', 
                        error_message = 'Cleaned orphaned MySQL data (PostgreSQL never committed)', 
                        mysql_user_id = NULL,
                        mysql_village_id = NULL,
                        coordinates_x = NULL,
                        coordinates_y = NULL,
                        completed_at = NOW() 
                    WHERE id = ?
                ")->execute([$recordId]);

                echo "  ✓ Successfully cleaned up orphaned MySQL data\n";
                $cleanedCount++;

            } catch (\Exception $e) {
                // Fix 1: Rollback MySQL transaction on any error
                if ($mysqlConn && $mysqlConn->inTransaction()) {
                    try {
                        $mysqlConn->rollBack();
                        echo "  ! MySQL transaction rolled back\n";
                    } catch (\Exception $rb) {
                        echo "  ! Rollback failed: " . $rb->getMessage() . "\n";
                    }
                }
                
                // Don't null IDs - leave them for retry
                // But update error state to track failures
                $errorMsg = "MySQL cleanup failed: " . $e->getMessage();
                echo "  ✗ $errorMsg\n";
                
                // Fix 3: Track failure count to prevent infinite retries
                $failureCount = ($record['retry_count'] ?? 0) + 1;
                
                if ($failureCount >= 5) {
                    // Fix 4: After 5 failures, mark as 'blocked' to alert operators
                    $db->prepare("
                        UPDATE pending_npc_creations 
                        SET status = 'blocked', 
                            error_message = ?, 
                            retry_count = ?
                        WHERE id = ?
                    ")->execute([$errorMsg, $failureCount, $recordId]);
                    echo "  ⚠ Marked as BLOCKED after $failureCount failures - manual intervention required\n";
                } else {
                    // Update error message but keep status for retry
                    $db->prepare("
                        UPDATE pending_npc_creations 
                        SET error_message = ?, 
                            retry_count = ?
                        WHERE id = ?
                    ")->execute([$errorMsg, $failureCount, $recordId]);
                    echo "  ⚠ Retry count: $failureCount/5\n";
                }
                $failedCount++;
            }

        } else {
            echo "  ! No data in either database\n";
            
            try {
                $db->prepare("
                    UPDATE pending_npc_creations 
                    SET status = 'failed', 
                        error_message = 'Timed out with no committed data', 
                        completed_at = NOW() 
                    WHERE id = ?
                ")->execute([$recordId]);

                echo "  ✓ Marked as failed (no data to clean)\n";
                $cleanedCount++;

            } catch (\Exception $e) {
                echo "  ✗ Failed to mark as failed: " . $e->getMessage() . "\n";
                $failedCount++;
            }
        }

        echo "\n";
    }

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Cleanup Summary:\n";
    echo "  Total processed:  " . count($stuckRecords) . "\n";
    echo "  Successfully cleaned: {$cleanedCount}\n";
    echo "  Failed:           {$failedCount}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    if ($cleanedCount > 0) {
        echo "\n✓ Cleanup completed successfully!\n";
        exit(0);
    } else {
        echo "\n⚠ Some records could not be cleaned. Check logs above.\n";
        exit(1);
    }

} catch (\Exception $e) {
    echo "\n✗ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

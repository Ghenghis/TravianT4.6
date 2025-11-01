<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use Exceptions\NotFoundException;
use Helpers\RedisCache;
use PDO;

class ReportsCtrl extends ApiAbstractCtrl
{
    public function getReports()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureReportTablesExist($serverDB);

        $type = $this->payload['type'] ?? 'all';
        $unreadOnly = isset($this->payload['unreadOnly']) && $this->payload['unreadOnly'];
        $limit = isset($this->payload['limit']) ? min((int)$this->payload['limit'], 100) : 50;
        $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;

        $queries = [];
        $isReadFilter = $unreadOnly ? "AND is_read = false" : "";

        if ($type === 'all' || $type === 'battle') {
            $queries[] = "
                SELECT id, 'battle' as report_type, uid, created_at, is_read, is_archived
                FROM reports_battle
                WHERE uid = :uid {$isReadFilter}
            ";
        }

        if ($type === 'all' || $type === 'trade') {
            $queries[] = "
                SELECT id, 'trade' as report_type, uid, created_at, is_read, is_archived
                FROM reports_trade
                WHERE uid = :uid {$isReadFilter}
            ";
        }

        if ($type === 'all' || $type === 'system') {
            $queries[] = "
                SELECT id, 'system' as report_type, uid, created_at, is_read, is_archived
                FROM reports_system
                WHERE uid = :uid {$isReadFilter}
            ";
        }

        if (empty($queries)) {
            $this->response = [
                'reports' => [],
                'total' => 0,
                'hasMore' => false
            ];
            return;
        }

        $countSql = "SELECT COUNT(*) as total FROM (
            " . implode(" UNION ALL ", $queries) . "
        ) as combined";

        $stmt = $serverDB->prepare($countSql);
        $stmt->execute(['uid' => $this->payload['uid']]);
        $total = (int)$stmt->fetchColumn();

        $sql = "
            SELECT * FROM (
                " . implode(" UNION ALL ", $queries) . "
            ) as combined
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $serverDB->prepare($sql);
        $stmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $reports = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reports[] = [
                'id' => (int)$row['id'],
                'type' => $row['report_type'],
                'isRead' => (bool)$row['is_read'],
                'isArchived' => (bool)$row['is_archived'],
                'createdAt' => $row['created_at']
            ];
        }

        $hasMore = ($offset + count($reports)) < $total;

        $this->response = [
            'reports' => $reports,
            'total' => $total,
            'hasMore' => $hasMore
        ];
    }

    public function getReportDetails()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['reportId'])) {
            throw new MissingParameterException('reportId');
        }
        if (!isset($this->payload['reportType'])) {
            throw new MissingParameterException('reportType');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureReportTablesExist($serverDB);

        $reportType = $this->payload['reportType'];
        $table = "reports_{$reportType}";
        
        $stmt = $serverDB->prepare("SELECT * FROM {$table} WHERE id=:id AND uid=:uid");
        $stmt->bindValue('id', $this->payload['reportId'], PDO::PARAM_INT);
        $stmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $stmt->execute();

        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            throw new NotFoundException('Report not found or access denied');
        }

        $serverDB->prepare("UPDATE {$table} SET is_read=true WHERE id=:id AND uid=:uid")
                  ->execute(['id' => $this->payload['reportId'], 'uid' => $this->payload['uid']]);

        if ($reportType === 'battle') {
            $this->response = $this->formatBattleReportDetailed($report);
        } elseif ($reportType === 'trade') {
            $this->response = $this->formatTradeReportDetailed($report);
        } else {
            $this->response = $this->formatSystemReportDetailed($report);
        }
    }

    public function markRead()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['reportIds']) || !is_array($this->payload['reportIds'])) {
            throw new MissingParameterException('reportIds');
        }
        if (!isset($this->payload['reportType'])) {
            throw new MissingParameterException('reportType');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureReportTablesExist($serverDB);

        $reportType = $this->payload['reportType'];
        $table = "reports_{$reportType}";
        $ids = array_map('intval', $this->payload['reportIds']);
        
        if (empty($ids)) {
            $this->response = ['success' => true, 'marked' => 0];
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;
        $params[] = $this->payload['uid'];
        $stmt = $serverDB->prepare("UPDATE {$table} SET is_read=true WHERE id IN ({$placeholders}) AND uid=?");
        $stmt->execute($params);

        $this->response = [
            'success' => true,
            'marked' => $stmt->rowCount()
        ];
    }

    public function deleteReport()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['reportIds']) || !is_array($this->payload['reportIds'])) {
            throw new MissingParameterException('reportIds');
        }
        if (!isset($this->payload['reportType'])) {
            throw new MissingParameterException('reportType');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureReportTablesExist($serverDB);

        $reportType = $this->payload['reportType'];
        $table = "reports_{$reportType}";
        $ids = array_map('intval', $this->payload['reportIds']);
        
        if (empty($ids)) {
            $this->response = ['success' => true, 'deleted' => 0];
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;
        $params[] = $this->payload['uid'];
        $stmt = $serverDB->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders}) AND is_archived=false AND uid=?");
        $stmt->execute($params);

        $this->response = [
            'success' => true,
            'deleted' => $stmt->rowCount()
        ];
    }

    public function archiveReport()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['reportId'])) {
            throw new MissingParameterException('reportId');
        }
        if (!isset($this->payload['reportType'])) {
            throw new MissingParameterException('reportType');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureReportTablesExist($serverDB);

        $reportType = $this->payload['reportType'];
        $table = "reports_{$reportType}";
        
        $stmt = $serverDB->prepare("UPDATE {$table} SET is_archived=true WHERE id=:id AND uid=:uid");
        $stmt->bindValue('id', $this->payload['reportId'], PDO::PARAM_INT);
        $stmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $stmt->execute();

        $this->response = [
            'success' => true,
            'archived' => $stmt->rowCount() > 0
        ];
    }

    public function getUnreadCount()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $cacheKey = "reports_unread:{$this->payload['worldId']}:{$this->payload['uid']}";
        $cache = RedisCache::getInstance();
        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            $this->response = $cached;
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureReportTablesExist($serverDB);

        $battleStmt = $serverDB->prepare("SELECT COUNT(*) FROM reports_battle WHERE uid=:uid AND is_read=false");
        $battleStmt->execute(['uid' => $this->payload['uid']]);
        $battleCount = (int)$battleStmt->fetchColumn();

        $tradeStmt = $serverDB->prepare("SELECT COUNT(*) FROM reports_trade WHERE uid=:uid AND is_read=false");
        $tradeStmt->execute(['uid' => $this->payload['uid']]);
        $tradeCount = (int)$tradeStmt->fetchColumn();

        $systemStmt = $serverDB->prepare("SELECT COUNT(*) FROM reports_system WHERE uid=:uid AND is_read=false");
        $systemStmt->execute(['uid' => $this->payload['uid']]);
        $systemCount = (int)$systemStmt->fetchColumn();

        $this->response = [
            'unreadCount' => [
                'battle' => $battleCount,
                'trade' => $tradeCount,
                'system' => $systemCount,
                'total' => $battleCount + $tradeCount + $systemCount
            ]
        ];

        $cache->set($cacheKey, $this->response, 30);
    }

    private function formatBattleReportSummary($row)
    {
        return [
            'id' => (int)$row['id'],
            'type' => 'battle',
            'result' => $row['battle_result'],
            'isRead' => (bool)$row['is_read'],
            'isArchived' => (bool)$row['is_archived'],
            'createdAt' => $row['created_at']
        ];
    }

    private function formatBattleReportDetailed($row)
    {
        return [
            'report' => [
                'id' => (int)$row['id'],
                'type' => 'battle',
                'attacker' => [
                    'userId' => (int)$row['attacker_uid'],
                    'villageId' => (int)$row['attacker_village_id'],
                    'troops' => $row['attacker_troops'] ? json_decode($row['attacker_troops'], true) : [],
                    'casualties' => $row['attacker_casualties'] ? json_decode($row['attacker_casualties'], true) : []
                ],
                'defender' => [
                    'userId' => (int)$row['defender_uid'],
                    'villageId' => (int)$row['defender_village_id'],
                    'troops' => $row['defender_troops'] ? json_decode($row['defender_troops'], true) : [],
                    'casualties' => $row['defender_casualties'] ? json_decode($row['defender_casualties'], true) : []
                ],
                'resourcesStolen' => $row['resources_stolen'] ? json_decode($row['resources_stolen'], true) : [],
                'result' => $row['battle_result'],
                'createdAt' => $row['created_at']
            ]
        ];
    }

    private function formatTradeReportSummary($row)
    {
        return [
            'id' => (int)$row['id'],
            'type' => 'trade',
            'tradeType' => $row['trade_type'],
            'isRead' => (bool)$row['is_read'],
            'isArchived' => (bool)$row['is_archived'],
            'createdAt' => $row['created_at']
        ];
    }

    private function formatTradeReportDetailed($row)
    {
        return [
            'report' => [
                'id' => (int)$row['id'],
                'type' => 'trade',
                'from' => (int)$row['village_from'],
                'to' => (int)$row['village_to'],
                'resources' => $row['resources'] ? json_decode($row['resources'], true) : [],
                'tradeType' => $row['trade_type'],
                'createdAt' => $row['created_at']
            ]
        ];
    }

    private function formatSystemReportSummary($row)
    {
        return [
            'id' => (int)$row['id'],
            'type' => 'system',
            'reportType' => $row['report_type'],
            'message' => substr($row['message'], 0, 100),
            'isRead' => (bool)$row['is_read'],
            'isArchived' => (bool)$row['is_archived'],
            'createdAt' => $row['created_at']
        ];
    }

    private function formatSystemReportDetailed($row)
    {
        return [
            'report' => [
                'id' => (int)$row['id'],
                'type' => 'system',
                'reportType' => $row['report_type'],
                'message' => $row['message'],
                'data' => $row['data'] ? json_decode($row['data'], true) : null,
                'createdAt' => $row['created_at']
            ]
        ];
    }

    private function ensureReportTablesExist($serverDB)
    {
        $serverDB->exec("CREATE TABLE IF NOT EXISTS reports_battle (
            id SERIAL PRIMARY KEY,
            uid INTEGER NOT NULL,
            attacker_uid INTEGER,
            attacker_village_id INTEGER,
            attacker_troops TEXT,
            attacker_casualties TEXT,
            defender_uid INTEGER,
            defender_village_id INTEGER,
            defender_troops TEXT,
            defender_casualties TEXT,
            resources_stolen TEXT,
            battle_result VARCHAR(20),
            is_read BOOLEAN DEFAULT FALSE,
            is_archived BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $serverDB->exec("CREATE TABLE IF NOT EXISTS reports_trade (
            id SERIAL PRIMARY KEY,
            uid INTEGER NOT NULL,
            village_from INTEGER NOT NULL,
            village_to INTEGER NOT NULL,
            resources TEXT,
            trade_type VARCHAR(20),
            is_read BOOLEAN DEFAULT FALSE,
            is_archived BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $serverDB->exec("CREATE TABLE IF NOT EXISTS reports_system (
            id SERIAL PRIMARY KEY,
            uid INTEGER NOT NULL,
            report_type VARCHAR(50),
            message TEXT,
            data TEXT,
            is_read BOOLEAN DEFAULT FALSE,
            is_archived BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $serverDB->exec("CREATE INDEX IF NOT EXISTS idx_reports_battle_uid_created_at ON reports_battle(uid, created_at DESC)");
        $serverDB->exec("CREATE INDEX IF NOT EXISTS idx_reports_battle_uid_is_read ON reports_battle(uid, is_read) WHERE is_read = false");

        $serverDB->exec("CREATE INDEX IF NOT EXISTS idx_reports_trade_uid_created_at ON reports_trade(uid, created_at DESC)");
        $serverDB->exec("CREATE INDEX IF NOT EXISTS idx_reports_trade_uid_is_read ON reports_trade(uid, is_read) WHERE is_read = false");

        $serverDB->exec("CREATE INDEX IF NOT EXISTS idx_reports_system_uid_created_at ON reports_system(uid, created_at DESC)");
        $serverDB->exec("CREATE INDEX IF NOT EXISTS idx_reports_system_uid_is_read ON reports_system(uid, is_read) WHERE is_read = false");
    }
}

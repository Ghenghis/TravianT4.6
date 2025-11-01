<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use Exceptions\NotFoundException;
use Helpers\RedisCache;
use PDO;

class StatisticsCtrl extends ApiAbstractCtrl
{
    public function getPlayerRankings()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['category'])) {
            throw new MissingParameterException('category');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureStatisticsTablesExist($serverDB);

        $category = $this->payload['category'];
        $limit = isset($this->payload['limit']) ? min((int)$this->payload['limit'], 100) : 50;
        $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;

        $cacheKey = "rankings:{$this->payload['worldId']}:{$category}:{$limit}:{$offset}";
        $cache = RedisCache::getInstance();
        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            $this->response = $cached;
            return;
        }

        $cacheStmt = $serverDB->prepare("
            SELECT * FROM leaderboard_cache 
            WHERE world_id=:wid AND category=:cat AND updated_at > NOW() - INTERVAL '5 minutes'
            ORDER BY rank ASC
            LIMIT :limit OFFSET :offset
        ");
        $cacheStmt->bindValue('wid', $this->payload['worldId'], PDO::PARAM_STR);
        $cacheStmt->bindValue('cat', $category, PDO::PARAM_STR);
        $cacheStmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $cacheStmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $cacheStmt->execute();

        if ($cacheStmt->rowCount() > 0) {
            $rankings = [];
            while ($row = $cacheStmt->fetch(PDO::FETCH_ASSOC)) {
                $rankings[] = [
                    'rank' => (int)$row['rank'],
                    'player' => [
                        'userId' => (int)$row['uid'],
                        'name' => $row['player_name']
                    ],
                    'alliance' => $row['alliance_id'] ? [
                        'id' => (int)$row['alliance_id'],
                        'tag' => $row['alliance_tag']
                    ] : null,
                    'value' => (int)$row['value']
                ];
            }

            $this->response = [
                'rankings' => $rankings,
                'category' => $category,
                'cached' => true
            ];
            return;
        }

        $orderColumn = $this->getCategoryColumn($category);
        if (!$orderColumn) {
            $this->response = ['error' => 'Invalid category'];
            return;
        }

        $query = "
            SELECT 
                u.id as uid, 
                u.name as player_name,
                u.alliance_id,
                a.tag as alliance_tag,
                {$orderColumn} as value
            FROM users u
            LEFT JOIN alliance a ON u.alliance_id = a.id
            ORDER BY value DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $serverDB->prepare($query);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rankings = [];
        $rank = $offset + 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $serverDB->prepare("
                INSERT INTO leaderboard_cache (world_id, category, rank, uid, value, player_name, alliance_id, alliance_tag, updated_at)
                VALUES (:wid, :cat, :rank, :uid, :val, :name, :aid, :tag, CURRENT_TIMESTAMP)
                ON CONFLICT (world_id, category, uid) 
                DO UPDATE SET rank=:rank, value=:val, updated_at=CURRENT_TIMESTAMP
            ")->execute([
                'wid' => $this->payload['worldId'],
                'cat' => $category,
                'rank' => $rank,
                'uid' => $row['uid'],
                'val' => $row['value'],
                'name' => $row['player_name'],
                'aid' => $row['alliance_id'],
                'tag' => $row['alliance_tag']
            ]);

            $rankings[] = [
                'rank' => $rank,
                'player' => [
                    'userId' => (int)$row['uid'],
                    'name' => $row['player_name']
                ],
                'alliance' => $row['alliance_id'] ? [
                    'id' => (int)$row['alliance_id'],
                    'tag' => $row['alliance_tag']
                ] : null,
                'value' => (int)$row['value']
            ];
            $rank++;
        }

        $this->response = [
            'rankings' => $rankings,
            'category' => $category,
            'cached' => false
        ];

        $cache->set($cacheKey, $this->response, 300);
    }

    public function getAllianceRankings()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureStatisticsTablesExist($serverDB);

        $limit = isset($this->payload['limit']) ? min((int)$this->payload['limit'], 100) : 50;
        $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;

        $cacheKey = "alliance_rankings:{$this->payload['worldId']}:{$limit}:{$offset}";
        $cache = RedisCache::getInstance();
        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            $this->response = $cached;
            return;
        }

        $stmt = $serverDB->prepare("
            SELECT 
                a.id,
                a.name,
                a.tag,
                COUNT(DISTINCT v.kid) as villages,
                SUM(v.pop) as population,
                COUNT(DISTINCT u.id) as members
            FROM alliance a
            LEFT JOIN users u ON a.id = u.alliance_id
            LEFT JOIN vdata v ON u.id = v.owner
            GROUP BY a.id, a.name, a.tag
            ORDER BY population DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rankings = [];
        $rank = $offset + 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rankings[] = [
                'rank' => $rank++,
                'alliance' => [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'tag' => $row['tag']
                ],
                'stats' => [
                    'population' => (int)$row['population'],
                    'villages' => (int)$row['villages'],
                    'members' => (int)$row['members']
                ]
            ];
        }

        $this->response = [
            'rankings' => $rankings,
            'total' => count($rankings)
        ];

        $cache->set($cacheKey, $this->response, 300);
    }

    public function getPlayerStats()
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
        $this->ensureStatisticsTablesExist($serverDB);

        $stmt = $serverDB->prepare("SELECT * FROM users WHERE id=:uid");
        $stmt->execute(['uid' => $this->payload['uid']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->response = ['error' => 'Player not found'];
            return;
        }

        $villagesStmt = $serverDB->prepare("
            SELECT COUNT(*) as count, SUM(pop) as population 
            FROM vdata WHERE owner=:uid
        ");
        $villagesStmt->execute(['uid' => $this->payload['uid']]);
        $villages = $villagesStmt->fetch(PDO::FETCH_ASSOC);

        $this->response = [
            'player' => [
                'userId' => (int)$user['id'],
                'name' => $user['name'],
                'tribe' => (int)$user['race'],
                'allianceId' => (int)$user['alliance_id'],
                'villages' => (int)$villages['count'],
                'population' => (int)$villages['population'],
                'gold' => isset($user['gold']) ? (int)$user['gold'] : 0
            ]
        ];
    }

    public function getAllianceStats()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['allianceId'])) {
            throw new MissingParameterException('allianceId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureStatisticsTablesExist($serverDB);

        $stmt = $serverDB->prepare("SELECT * FROM alliance WHERE id=:aid");
        $stmt->execute(['aid' => $this->payload['allianceId']]);
        $alliance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alliance) {
            $this->response = ['error' => 'Alliance not found'];
            return;
        }

        $statsStmt = $serverDB->prepare("
            SELECT 
                COUNT(DISTINCT u.id) as members,
                COUNT(DISTINCT v.kid) as villages,
                SUM(v.pop) as population
            FROM users u
            LEFT JOIN vdata v ON u.id = v.owner
            WHERE u.alliance_id=:aid
        ");
        $statsStmt->execute(['aid' => $this->payload['allianceId']]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        $this->response = [
            'alliance' => [
                'id' => (int)$alliance['id'],
                'name' => $alliance['name'],
                'tag' => $alliance['tag'],
                'description' => $alliance['description'] ?? '',
                'members' => (int)$stats['members'],
                'villages' => (int)$stats['villages'],
                'population' => (int)$stats['population']
            ]
        ];
    }

    public function getTop10()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureStatisticsTablesExist($serverDB);

        $categories = ['population', 'attack', 'defense'];
        $results = [];

        foreach ($categories as $category) {
            $column = $this->getCategoryColumn($category);
            if (!$column) continue;

            $stmt = $serverDB->prepare("
                SELECT u.id, u.name, {$column} as value
                FROM users u
                ORDER BY value DESC
                LIMIT 10
            ");
            $stmt->execute();

            $top10 = [];
            $rank = 1;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $top10[] = [
                    'rank' => $rank++,
                    'player' => [
                        'userId' => (int)$row['id'],
                        'name' => $row['name']
                    ],
                    'value' => (int)$row['value']
                ];
            }

            $results[$category] = $top10;
        }

        $this->response = [
            'top10' => $results
        ];
    }

    public function getWorldStats()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureStatisticsTablesExist($serverDB);

        $playersStmt = $serverDB->query("SELECT COUNT(*) FROM users WHERE access >= 1");
        $totalPlayers = (int)$playersStmt->fetchColumn();

        $villagesStmt = $serverDB->query("SELECT COUNT(*) as count, SUM(pop) as population FROM vdata WHERE owner > 0");
        $villages = $villagesStmt->fetch(PDO::FETCH_ASSOC);

        $alliancesStmt = $serverDB->query("SELECT COUNT(*) FROM alliance");
        $totalAlliances = (int)$alliancesStmt->fetchColumn();

        $this->response = [
            'worldStats' => [
                'totalPlayers' => $totalPlayers,
                'totalVillages' => (int)$villages['count'],
                'totalPopulation' => (int)$villages['population'],
                'totalAlliances' => $totalAlliances
            ]
        ];
    }

    private function getCategoryColumn($category)
    {
        $mapping = [
            'population' => '(SELECT COALESCE(SUM(pop), 0) FROM vdata WHERE owner=u.id)',
            'attack' => 'attackPoints',
            'defense' => 'defensePoints',
            'villages' => '(SELECT COUNT(*) FROM vdata WHERE owner=u.id)'
        ];

        return $mapping[$category] ?? null;
    }

    private function ensureStatisticsTablesExist($serverDB)
    {
        $serverDB->exec("CREATE TABLE IF NOT EXISTS statistics_snapshots (
            id SERIAL PRIMARY KEY,
            total_players INTEGER DEFAULT 0,
            total_villages INTEGER DEFAULT 0,
            total_alliances INTEGER DEFAULT 0,
            total_population INTEGER DEFAULT 0,
            snapshot_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $serverDB->exec("CREATE TABLE IF NOT EXISTS leaderboard_cache (
            id SERIAL PRIMARY KEY,
            world_id VARCHAR(50),
            category VARCHAR(50),
            rank INTEGER,
            uid INTEGER,
            value INTEGER,
            player_name VARCHAR(255),
            alliance_id INTEGER,
            alliance_tag VARCHAR(20),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(world_id, category, uid)
        )");
    }
}

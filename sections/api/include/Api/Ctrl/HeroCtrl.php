<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use Exceptions\NotFoundException;
use PDO;

class HeroCtrl extends ApiAbstractCtrl
{
    public function getHero()
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
        
        $this->ensureHeroTablesExist($serverDB);
        
        $stmt = $serverDB->prepare("SELECT * FROM hero_profile WHERE uid=:uid");
        $stmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $stmt->execute();
        $hero = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hero) {
            $createStmt = $serverDB->prepare("
                INSERT INTO hero_profile (uid, health, level, experience, strength, attack_bonus, defense_bonus, resource_bonus, attribute_points, silver, x, y)
                VALUES (:uid, 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
            ");
            $createStmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
            $createStmt->execute();

            $stmt->execute();
            $hero = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $itemsStmt = $serverDB->prepare("SELECT * FROM hero_items WHERE uid=:uid AND equipped=true");
        $itemsStmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $itemsStmt->execute();
        $equipment = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $xpNeeded = ((int)$hero['level'] + 1) * 100;

        $this->response = [
            'hero' => [
                'health' => (int)$hero['health'],
                'level' => (int)$hero['level'],
                'experience' => (int)$hero['experience'],
                'experienceNeeded' => $xpNeeded,
                'silver' => (int)$hero['silver'],
                'position' => [
                    'x' => (int)$hero['x'],
                    'y' => (int)$hero['y']
                ],
                'attributes' => [
                    'strength' => (int)$hero['strength'],
                    'attackBonus' => (int)$hero['attack_bonus'],
                    'defenseBonus' => (int)$hero['defense_bonus'],
                    'resourceBonus' => (int)$hero['resource_bonus'],
                    'pointsAvailable' => (int)$hero['attribute_points']
                ],
                'equipment' => array_map(function($item) {
                    return [
                        'id' => (int)$item['id'],
                        'type' => $item['item_type'],
                        'tier' => (int)$item['tier'],
                        'slot' => $item['slot'],
                        'stats' => $item['stats'] ? json_decode($item['stats'], true) : []
                    ];
                }, $equipment)
            ]
        ];
    }

    public function levelUp()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['attributes'])) {
            throw new MissingParameterException('attributes');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureHeroTablesExist($serverDB);

        $attrs = $this->payload['attributes'];
        $totalSpent = ($attrs['strength'] ?? 0) + ($attrs['attackBonus'] ?? 0) + 
                      ($attrs['defenseBonus'] ?? 0) + ($attrs['resourceBonus'] ?? 0);

        $stmt = $serverDB->prepare("SELECT * FROM hero_profile WHERE uid=:uid");
        $stmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $stmt->execute();
        $hero = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hero || (int)$hero['attribute_points'] < $totalSpent) {
            $this->response = ['error' => 'Not enough attribute points'];
            return;
        }

        $updateStmt = $serverDB->prepare("
            UPDATE hero_profile 
            SET strength = strength + :str,
                attack_bonus = attack_bonus + :atk,
                defense_bonus = defense_bonus + :def,
                resource_bonus = resource_bonus + :res,
                attribute_points = attribute_points - :spent,
                updated_at = CURRENT_TIMESTAMP
            WHERE uid = :uid
        ");
        $updateStmt->execute([
            'str' => $attrs['strength'] ?? 0,
            'atk' => $attrs['attackBonus'] ?? 0,
            'def' => $attrs['defenseBonus'] ?? 0,
            'res' => $attrs['resourceBonus'] ?? 0,
            'spent' => $totalSpent,
            'uid' => $this->payload['uid']
        ]);

        $stmt->execute();
        $hero = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->response = [
            'success' => true,
            'attributes' => [
                'strength' => (int)$hero['strength'],
                'attackBonus' => (int)$hero['attack_bonus'],
                'defenseBonus' => (int)$hero['defense_bonus'],
                'resourceBonus' => (int)$hero['resource_bonus'],
                'pointsRemaining' => (int)$hero['attribute_points']
            ]
        ];
    }

    public function equipItem()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['itemId'])) {
            throw new MissingParameterException('itemId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureHeroTablesExist($serverDB);

        $stmt = $serverDB->prepare("SELECT * FROM hero_items WHERE id=:id AND uid=:uid");
        $stmt->execute([
            'id' => $this->payload['itemId'],
            'uid' => $this->payload['uid']
        ]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $this->response = ['error' => 'Item not found'];
            return;
        }

        $serverDB->prepare("UPDATE hero_items SET equipped=false WHERE uid=:uid AND slot=:slot")
                  ->execute(['uid' => $this->payload['uid'], 'slot' => $item['slot']]);

        $serverDB->prepare("UPDATE hero_items SET equipped=true WHERE id=:id")
                  ->execute(['id' => $this->payload['itemId']]);

        $this->response = [
            'success' => true,
            'equipped' => [
                'id' => (int)$item['id'],
                'type' => $item['item_type'],
                'tier' => (int)$item['tier'],
                'slot' => $item['slot']
            ]
        ];
    }

    public function startAdventure()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['adventureId'])) {
            throw new MissingParameterException('adventureId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureHeroTablesExist($serverDB);

        $heroStmt = $serverDB->prepare("SELECT * FROM hero_profile WHERE uid=:uid");
        $heroStmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $heroStmt->execute();
        $hero = $heroStmt->fetch(PDO::FETCH_ASSOC);

        if (!$hero || (int)$hero['health'] <= 0) {
            $this->response = ['error' => 'Hero health too low'];
            return;
        }

        $advStmt = $serverDB->prepare("SELECT * FROM hero_adventures WHERE id=:id AND status='available'");
        $advStmt->execute(['id' => $this->payload['adventureId']]);
        $adventure = $advStmt->fetch(PDO::FETCH_ASSOC);

        if (!$adventure) {
            $this->response = ['error' => 'Adventure not found or unavailable'];
            return;
        }

        $heroX = (int)$hero['x'];
        $heroY = (int)$hero['y'];
        $advX = (int)$adventure['x'];
        $advY = (int)$adventure['y'];
        
        $distance = sqrt(pow($advX - $heroX, 2) + pow($advY - $heroY, 2));
        $difficulty = (int)$adventure['difficulty'];
        $heroLevel = (int)$hero['level'];
        
        $baseDuration = 3600;
        $duration = $baseDuration + ($distance * 60) - ($heroLevel * 10);
        $duration = max($duration, 600);
        
        $baseReward = $difficulty * 100;
        $resourceBonus = (int)$hero['resource_bonus'];
        $resourceReward = (int)($baseReward * (1 + $resourceBonus / 100));
        $xpReward = (int)($difficulty * 10 * (1 + $heroLevel / 10));
        
        $itemChance = min(10 + $difficulty * 2, 50);
        $hasItem = (rand(1, 100) <= $itemChance);
        
        $rewards = [
            'resources' => [
                'wood' => $resourceReward,
                'clay' => $resourceReward,
                'iron' => $resourceReward,
                'crop' => $resourceReward
            ],
            'experience' => $xpReward,
            'items' => $hasItem ? 1 : 0
        ];

        $startTime = time();
        $endTime = $startTime + $duration;

        $updateStmt = $serverDB->prepare("
            UPDATE hero_adventures 
            SET status='in_progress', start_time=:start, end_time=:end, rewards=:rewards 
            WHERE id=:id
        ");
        $updateStmt->execute([
            'start' => $startTime,
            'end' => $endTime,
            'rewards' => json_encode($rewards),
            'id' => $this->payload['adventureId']
        ]);

        $this->response = [
            'success' => true,
            'adventure' => [
                'id' => (int)$adventure['id'],
                'duration' => $duration,
                'arrivalTime' => $endTime,
                'distance' => round($distance, 2),
                'difficulty' => $difficulty,
                'estimatedRewards' => $rewards
            ]
        ];
    }

    public function getAdventures()
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
        $this->ensureHeroTablesExist($serverDB);

        $heroStmt = $serverDB->prepare("SELECT x, y FROM hero_profile WHERE uid=:uid");
        $heroStmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $heroStmt->execute();
        $hero = $heroStmt->fetch(PDO::FETCH_ASSOC);
        
        $heroX = (int)($hero['x'] ?? 0);
        $heroY = (int)($hero['y'] ?? 0);

        $stmt = $serverDB->prepare("SELECT * FROM hero_adventures WHERE uid=:uid OR uid=0 ORDER BY created_at DESC LIMIT 20");
        $stmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $stmt->execute();

        $adventures = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $advX = (int)$row['x'];
            $advY = (int)$row['y'];
            $distance = sqrt(pow($advX - $heroX, 2) + pow($advY - $heroY, 2));

            $adventures[] = [
                'id' => (int)$row['id'],
                'coordinates' => ['x' => $advX, 'y' => $advY],
                'difficulty' => (int)$row['difficulty'],
                'distance' => round($distance, 2),
                'duration' => (int)$row['duration'],
                'status' => $row['status'],
                'endTime' => $row['status'] === 'in_progress' ? (int)$row['end_time'] : null
            ];
        }

        $this->response = [
            'adventures' => $adventures,
            'total' => count($adventures)
        ];
    }

    public function sellItem()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['itemId'])) {
            throw new MissingParameterException('itemId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureHeroTablesExist($serverDB);

        $stmt = $serverDB->prepare("SELECT * FROM hero_items WHERE id=:id AND uid=:uid");
        $stmt->execute([
            'id' => $this->payload['itemId'],
            'uid' => $this->payload['uid']
        ]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $this->response = ['error' => 'Item not found'];
            return;
        }

        if ($item['equipped']) {
            $this->response = ['error' => 'Cannot sell equipped item'];
            return;
        }

        $tier = (int)$item['tier'];
        $silverValue = $tier * 50 + 100;

        $serverDB->prepare("DELETE FROM hero_items WHERE id=:id")->execute(['id' => $this->payload['itemId']]);
        $serverDB->prepare("UPDATE hero_profile SET silver=silver+:silver WHERE uid=:uid")
                  ->execute(['silver' => $silverValue, 'uid' => $this->payload['uid']]);

        $heroStmt = $serverDB->prepare("SELECT silver FROM hero_profile WHERE uid=:uid");
        $heroStmt->execute(['uid' => $this->payload['uid']]);
        $newBalance = (int)$heroStmt->fetchColumn();

        $this->response = [
            'success' => true,
            'silverGained' => $silverValue,
            'newBalance' => $newBalance
        ];
    }

    public function auctionItem()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['itemId'])) {
            throw new MissingParameterException('itemId');
        }
        if (!isset($this->payload['startingBid'])) {
            throw new MissingParameterException('startingBid');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureHeroTablesExist($serverDB);

        $stmt = $serverDB->prepare("SELECT * FROM hero_items WHERE id=:id AND uid=:uid AND equipped=false");
        $stmt->execute([
            'id' => $this->payload['itemId'],
            'uid' => $this->payload['uid']
        ]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $this->response = ['error' => 'Item not found or equipped'];
            return;
        }

        $duration = isset($this->payload['duration']) ? (int)$this->payload['duration'] : 86400;
        $endTime = time() + $duration;

        $createStmt = $serverDB->prepare("
            INSERT INTO auctions (seller_uid, item_id, starting_bid, current_bid, end_time, status)
            VALUES (:uid, :itemId, :bid, :bid, :end, 'active')
        ");
        $createStmt->execute([
            'uid' => $this->payload['uid'],
            'itemId' => $this->payload['itemId'],
            'bid' => $this->payload['startingBid'],
            'end' => $endTime
        ]);

        $this->response = [
            'success' => true,
            'auctionId' => (int)$serverDB->lastInsertId(),
            'endTime' => $endTime
        ];
    }

    public function bidOnAuction()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['auctionId'])) {
            throw new MissingParameterException('auctionId');
        }
        if (!isset($this->payload['bidAmount'])) {
            throw new MissingParameterException('bidAmount');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureHeroTablesExist($serverDB);

        $stmt = $serverDB->prepare("SELECT * FROM auctions WHERE id=:id AND status='active' AND end_time > :now");
        $stmt->execute(['id' => $this->payload['auctionId'], 'now' => time()]);
        $auction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$auction) {
            $this->response = ['error' => 'Auction not found or expired'];
            return;
        }

        if ((int)$this->payload['bidAmount'] <= (int)$auction['current_bid']) {
            $this->response = ['error' => 'Bid must be higher than current bid'];
            return;
        }

        $heroStmt = $serverDB->prepare("SELECT silver FROM hero_profile WHERE uid=:uid");
        $heroStmt->execute(['uid' => $this->payload['uid']]);
        $silver = (int)$heroStmt->fetchColumn();

        if ($silver < (int)$this->payload['bidAmount']) {
            $this->response = ['error' => 'Insufficient silver'];
            return;
        }

        $updateStmt = $serverDB->prepare("
            UPDATE auctions 
            SET current_bid=:bid, highest_bidder_uid=:uid 
            WHERE id=:id
        ");
        $updateStmt->execute([
            'bid' => $this->payload['bidAmount'],
            'uid' => $this->payload['uid'],
            'id' => $this->payload['auctionId']
        ]);

        $this->response = [
            'success' => true,
            'currentBid' => (int)$this->payload['bidAmount'],
            'timeRemaining' => (int)$auction['end_time'] - time()
        ];
    }

    public function getAuctions()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureHeroTablesExist($serverDB);

        $limit = isset($this->payload['limit']) ? min((int)$this->payload['limit'], 100) : 50;
        $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;

        $stmt = $serverDB->prepare("
            SELECT a.*, i.item_type, i.tier, i.slot, u.name as seller_name
            FROM auctions a
            JOIN hero_items i ON a.item_id = i.id
            JOIN users u ON a.seller_uid = u.id
            WHERE a.status='active' AND a.end_time > :now
            ORDER BY a.end_time ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('now', time(), PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $auctions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $auctions[] = [
                'id' => (int)$row['id'],
                'item' => [
                    'type' => $row['item_type'],
                    'tier' => (int)$row['tier'],
                    'slot' => $row['slot']
                ],
                'seller' => $row['seller_name'],
                'currentBid' => (int)$row['current_bid'],
                'timeRemaining' => (int)$row['end_time'] - time(),
                'endTime' => (int)$row['end_time']
            ];
        }

        $countStmt = $serverDB->prepare("SELECT COUNT(*) FROM auctions WHERE status='active' AND end_time > :now");
        $countStmt->execute(['now' => time()]);
        $total = (int)$countStmt->fetchColumn();

        $this->response = [
            'auctions' => $auctions,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ];
    }

    private function ensureHeroTablesExist($serverDB)
    {
        $serverDB->exec("CREATE TABLE IF NOT EXISTS hero_profile (
            uid INTEGER PRIMARY KEY,
            health INTEGER DEFAULT 100,
            level INTEGER DEFAULT 0,
            experience INTEGER DEFAULT 0,
            strength INTEGER DEFAULT 0,
            attack_bonus INTEGER DEFAULT 0,
            defense_bonus INTEGER DEFAULT 0,
            resource_bonus INTEGER DEFAULT 0,
            attribute_points INTEGER DEFAULT 0,
            silver INTEGER DEFAULT 0,
            x INTEGER DEFAULT 0,
            y INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $serverDB->exec("CREATE TABLE IF NOT EXISTS hero_adventures (
            id SERIAL PRIMARY KEY,
            uid INTEGER NOT NULL,
            x INTEGER NOT NULL,
            y INTEGER NOT NULL,
            difficulty INTEGER DEFAULT 0,
            duration INTEGER DEFAULT 3600,
            start_time INTEGER,
            end_time INTEGER,
            status VARCHAR(20) DEFAULT 'available',
            rewards TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $serverDB->exec("CREATE TABLE IF NOT EXISTS hero_items (
            id SERIAL PRIMARY KEY,
            uid INTEGER NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            tier INTEGER DEFAULT 1,
            slot VARCHAR(20),
            equipped BOOLEAN DEFAULT FALSE,
            stats TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $serverDB->exec("CREATE TABLE IF NOT EXISTS auctions (
            id SERIAL PRIMARY KEY,
            seller_uid INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            starting_bid INTEGER NOT NULL,
            current_bid INTEGER DEFAULT 0,
            highest_bidder_uid INTEGER,
            end_time INTEGER NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
}

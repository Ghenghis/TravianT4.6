<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use Exceptions\NotFoundException;
use PDO;

class MarketCtrl extends ApiAbstractCtrl
{
    public function getOffers()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['villageId'])) {
            throw new MissingParameterException('villageId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $limit = isset($this->payload['limit']) ? min((int)$this->payload['limit'], 50) : 20;
        $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'market')");
        $tableExists = $tableCheck->fetchColumn();

        if (!$tableExists) {
            $this->response = [
                'offers' => [],
                'total' => 0,
                'message' => 'Market table not available'
            ];
            return;
        }

        $villageStmt = $serverDB->prepare("SELECT kid FROM vdata WHERE kid = :kid");
        $villageStmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
        $villageStmt->execute();

        if (!$villageStmt->rowCount()) {
            $this->response = ['error' => 'Village not found'];
            return;
        }

        $stmt = $serverDB->prepare("
            SELECT m.*, v.name as seller_village, u.name as seller_name
            FROM market m
            LEFT JOIN vdata v ON m.from_village = v.kid
            LEFT JOIN users u ON v.owner = u.id
            WHERE m.status = 'active'
            ORDER BY m.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $offers = [];
        $villageCoords = $this->kidToCoords((int)$this->payload['villageId']);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sellerCoords = $this->kidToCoords((int)$row['from_village']);
            $distance = $this->calculateDistance(
                $villageCoords['x'], 
                $villageCoords['y'], 
                $sellerCoords['x'], 
                $sellerCoords['y']
            );

            $offers[] = [
                'id' => (int)$row['id'],
                'seller' => [
                    'villageId' => (int)$row['from_village'],
                    'villageName' => $row['seller_village'],
                    'playerName' => $row['seller_name'],
                    'coordinates' => $sellerCoords
                ],
                'offering' => $this->parseResources($row['offering'] ?? ''),
                'requesting' => $this->parseResources($row['requesting'] ?? ''),
                'ratio' => (float)($row['ratio'] ?? 1.0),
                'distance' => round($distance, 2),
                'createdAt' => (int)($row['created_at'] ?? time())
            ];
        }

        $countStmt = $serverDB->prepare("SELECT COUNT(*) FROM market WHERE status = 'active'");
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $this->response = [
            'offers' => $offers,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ];
    }

    public function createOffer()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['villageId'])) {
            throw new MissingParameterException('villageId');
        }
        if (!isset($this->payload['offering'])) {
            throw new MissingParameterException('offering');
        }
        if (!isset($this->payload['requesting'])) {
            throw new MissingParameterException('requesting');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $offering = $this->payload['offering'];
        $requesting = $this->payload['requesting'];

        if (!isset($offering['resource']) || !isset($offering['amount'])) {
            $this->response = ['error' => 'Invalid offering format'];
            return;
        }

        if (!isset($requesting['resource']) || !isset($requesting['amount'])) {
            $this->response = ['error' => 'Invalid requesting format'];
            return;
        }

        $validResources = ['wood', 'clay', 'iron', 'crop'];
        if (!in_array($offering['resource'], $validResources) || !in_array($requesting['resource'], $validResources)) {
            $this->response = ['error' => 'Invalid resource type'];
            return;
        }

        if ($offering['amount'] <= 0 || $requesting['amount'] <= 0) {
            $this->response = ['error' => 'Amount must be positive'];
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $villageStmt = $serverDB->prepare("SELECT * FROM vdata WHERE kid = :kid");
        $villageStmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
        $villageStmt->execute();

        if (!$villageStmt->rowCount()) {
            $this->response = ['error' => 'Village not found'];
            return;
        }

        $village = $villageStmt->fetch(PDO::FETCH_ASSOC);
        $offeringResource = $offering['resource'];
        $offeringAmount = (int)$offering['amount'];

        if ((int)$village[$offeringResource] < $offeringAmount) {
            $this->response = [
                'error' => 'Insufficient resources',
                'required' => $offeringAmount,
                'available' => (int)$village[$offeringResource]
            ];
            return;
        }

        $marketplaceFee = (int)($offeringAmount * 0.03);
        $ratio = (float)$requesting['amount'] / $offering['amount'];

        $this->response = [
            'success' => true,
            'message' => 'Offer created (simulation mode)',
            'offerId' => rand(1000, 9999),
            'offering' => $offering,
            'requesting' => $requesting,
            'ratio' => round($ratio, 3),
            'fee' => $marketplaceFee,
            'createdAt' => time()
        ];
    }

    public function sendResources()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['fromVillageId'])) {
            throw new MissingParameterException('fromVillageId');
        }
        if (!isset($this->payload['toX']) || !isset($this->payload['toY'])) {
            throw new MissingParameterException('toX, toY');
        }
        if (!isset($this->payload['resources'])) {
            throw new MissingParameterException('resources');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $resources = $this->payload['resources'];
        $totalResources = 0;

        foreach (['wood', 'clay', 'iron', 'crop'] as $resource) {
            $amount = (int)($resources[$resource] ?? 0);
            if ($amount < 0) {
                $this->response = ['error' => 'Resource amounts must be positive'];
                return;
            }
            $totalResources += $amount;
        }

        if ($totalResources <= 0) {
            $this->response = ['error' => 'Must send at least some resources'];
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $fromVillageStmt = $serverDB->prepare("SELECT * FROM vdata WHERE kid = :kid");
        $fromVillageStmt->bindValue('kid', $this->payload['fromVillageId'], PDO::PARAM_INT);
        $fromVillageStmt->execute();

        if (!$fromVillageStmt->rowCount()) {
            $this->response = ['error' => 'Source village not found'];
            return;
        }

        $village = $fromVillageStmt->fetch(PDO::FETCH_ASSOC);

        foreach (['wood', 'clay', 'iron', 'crop'] as $resource) {
            $amount = (int)($resources[$resource] ?? 0);
            if ((int)$village[$resource] < $amount) {
                $this->response = [
                    'error' => "Insufficient {$resource}",
                    'required' => $amount,
                    'available' => (int)$village[$resource]
                ];
                return;
            }
        }

        $merchantsUsed = (int)ceil($totalResources / 500);
        $merchantCapacity = 10;

        if ($merchantsUsed > $merchantCapacity) {
            $this->response = [
                'error' => 'Insufficient merchants',
                'required' => $merchantsUsed,
                'available' => $merchantCapacity
            ];
            return;
        }

        $fromCoords = $this->kidToCoords((int)$this->payload['fromVillageId']);
        $toCoords = ['x' => (int)$this->payload['toX'], 'y' => (int)$this->payload['toY']];
        $distance = $this->calculateDistance($fromCoords['x'], $fromCoords['y'], $toCoords['x'], $toCoords['y']);

        $merchantSpeed = 16;
        $worldSpeed = $server['speed'] ?? 1;
        $travelTime = ($distance * 3600) / ($merchantSpeed * $worldSpeed);
        $arrivalTime = time() + (int)$travelTime;

        $this->response = [
            'success' => true,
            'message' => 'Resources sent (simulation mode)',
            'merchantsUsed' => $merchantsUsed,
            'totalResources' => $totalResources,
            'distance' => round($distance, 2),
            'travelTime' => (int)$travelTime,
            'arrivalTime' => $arrivalTime,
            'from' => $fromCoords,
            'to' => $toCoords
        ];
    }

    public function acceptOffer()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['villageId'])) {
            throw new MissingParameterException('villageId');
        }
        if (!isset($this->payload['offerId'])) {
            throw new MissingParameterException('offerId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'market')");
        $tableExists = $tableCheck->fetchColumn();

        if (!$tableExists) {
            $this->response = ['error' => 'Market table not available'];
            return;
        }

        $offerStmt = $serverDB->prepare("SELECT * FROM market WHERE id = :id AND status = 'active'");
        $offerStmt->bindValue('id', $this->payload['offerId'], PDO::PARAM_INT);
        $offerStmt->execute();

        if (!$offerStmt->rowCount()) {
            $this->response = ['error' => 'Offer not found or already accepted'];
            return;
        }

        $offer = $offerStmt->fetch(PDO::FETCH_ASSOC);

        $villageStmt = $serverDB->prepare("SELECT * FROM vdata WHERE kid = :kid");
        $villageStmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
        $villageStmt->execute();

        if (!$villageStmt->rowCount()) {
            $this->response = ['error' => 'Village not found'];
            return;
        }

        $village = $villageStmt->fetch(PDO::FETCH_ASSOC);

        $requesting = $this->parseResources($offer['requesting'] ?? '');
        $offering = $this->parseResources($offer['offering'] ?? '');

        if (empty($requesting) || empty($offering)) {
            $this->response = ['error' => 'Invalid offer data'];
            return;
        }

        $fromCoords = $this->kidToCoords((int)$this->payload['villageId']);
        $toCoords = $this->kidToCoords((int)$offer['from_village']);
        $distance = $this->calculateDistance($fromCoords['x'], $fromCoords['y'], $toCoords['x'], $toCoords['y']);

        $merchantSpeed = 16;
        $worldSpeed = $server['speed'] ?? 1;
        $travelTime = ($distance * 3600) / ($merchantSpeed * $worldSpeed);
        $arrivalTime = time() + (int)$travelTime;

        $this->response = [
            'success' => true,
            'message' => 'Offer accepted (simulation mode)',
            'transactionId' => rand(10000, 99999),
            'offering' => $offering,
            'requesting' => $requesting,
            'distance' => round($distance, 2),
            'travelTime' => (int)$travelTime,
            'arrivalTime' => $arrivalTime
        ];
    }

    public function getTradeHistory()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['villageId'])) {
            throw new MissingParameterException('villageId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $limit = isset($this->payload['limit']) ? min((int)$this->payload['limit'], 100) : 20;
        $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'market')");
        $tableExists = $tableCheck->fetchColumn();

        if (!$tableExists) {
            $this->response = [
                'trades' => [],
                'total' => 0,
                'message' => 'Market table not available'
            ];
            return;
        }

        $stmt = $serverDB->prepare("
            SELECT m.*, 
                   v1.name as from_village_name, 
                   v2.name as to_village_name,
                   u1.name as from_player_name,
                   u2.name as to_player_name
            FROM market m
            LEFT JOIN vdata v1 ON m.from_village = v1.kid
            LEFT JOIN vdata v2 ON m.to_village = v2.kid
            LEFT JOIN users u1 ON v1.owner = u1.id
            LEFT JOIN users u2 ON v2.owner = u2.id
            WHERE (m.from_village = :villageId OR m.to_village = :villageId)
              AND m.status = 'completed'
            ORDER BY m.completed_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('villageId', $this->payload['villageId'], PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $trades = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $isOutgoing = (int)$row['from_village'] === (int)$this->payload['villageId'];
            
            $trades[] = [
                'id' => (int)$row['id'],
                'type' => $isOutgoing ? 'outgoing' : 'incoming',
                'partner' => [
                    'villageName' => $isOutgoing ? $row['to_village_name'] : $row['from_village_name'],
                    'playerName' => $isOutgoing ? $row['to_player_name'] : $row['from_player_name']
                ],
                'offering' => $this->parseResources($row['offering'] ?? ''),
                'requesting' => $this->parseResources($row['requesting'] ?? ''),
                'timestamp' => (int)($row['completed_at'] ?? time())
            ];
        }

        $countStmt = $serverDB->prepare("
            SELECT COUNT(*) FROM market 
            WHERE (from_village = :villageId OR to_village = :villageId)
              AND status = 'completed'
        ");
        $countStmt->bindValue('villageId', $this->payload['villageId'], PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $this->response = [
            'trades' => $trades,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ];
    }

    private function parseResources($resourceString)
    {
        if (empty($resourceString)) {
            return ['wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0];
        }

        if (is_array($resourceString)) {
            return $resourceString;
        }

        $parts = explode(',', $resourceString);
        return [
            'wood' => (int)($parts[0] ?? 0),
            'clay' => (int)($parts[1] ?? 0),
            'iron' => (int)($parts[2] ?? 0),
            'crop' => (int)($parts[3] ?? 0)
        ];
    }

    private function kidToCoords($kid)
    {
        $y = floor($kid / 801) - 200;
        $x = ($kid % 801) - 200;
        return ['x' => (int)$x, 'y' => (int)$y];
    }

    private function calculateDistance($x1, $y1, $x2, $y2)
    {
        return sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
    }
}

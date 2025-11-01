<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use Exceptions\NotFoundException;
use PDO;

class TroopCtrl extends ApiAbstractCtrl
{
    public function getTroops()
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

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        
        $stmt = $serverDB->prepare("SELECT * FROM units WHERE kid = :kid");
        $stmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
        $stmt->execute();

        if (!$stmt->rowCount()) {
            $troops = [];
            for ($i = 1; $i <= 11; $i++) {
                $troops["u{$i}"] = 0;
            }
        } else {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $troops = [];
            for ($i = 1; $i <= 11; $i++) {
                $troops["u{$i}"] = (int)($row["u{$i}"] ?? 0);
            }
        }

        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'movement')");
        $tableExists = $tableCheck->fetchColumn();

        $incoming = 0;
        $outgoing = 0;

        if ($tableExists) {
            $incomingStmt = $serverDB->prepare("SELECT COUNT(*) FROM movement WHERE to_kid = :kid AND end_time > :now");
            $incomingStmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
            $incomingStmt->bindValue('now', time(), PDO::PARAM_INT);
            $incomingStmt->execute();
            $incoming = (int)$incomingStmt->fetchColumn();

            $outgoingStmt = $serverDB->prepare("SELECT COUNT(*) FROM movement WHERE from_kid = :kid AND end_time > :now");
            $outgoingStmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
            $outgoingStmt->bindValue('now', time(), PDO::PARAM_INT);
            $outgoingStmt->execute();
            $outgoing = (int)$outgoingStmt->fetchColumn();
        }

        $this->response = [
            'troops' => $troops,
            'incoming' => $incoming,
            'outgoing' => $outgoing
        ];
    }

    public function trainUnits()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['villageId'])) {
            throw new MissingParameterException('villageId');
        }
        if (!isset($this->payload['unitType'])) {
            throw new MissingParameterException('unitType');
        }
        if (!isset($this->payload['quantity'])) {
            throw new MissingParameterException('quantity');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $unitType = (int)$this->payload['unitType'];
        $quantity = (int)$this->payload['quantity'];

        if ($quantity <= 0 || $quantity > 1000) {
            $this->response = ['error' => 'Invalid quantity (1-1000)'];
            return;
        }

        if ($unitType < 1 || $unitType > 11) {
            $this->response = ['error' => 'Invalid unit type (1-11)'];
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

        $baseCosts = [
            1 => ['wood' => 120, 'clay' => 100, 'iron' => 150, 'crop' => 30, 'time' => 1400, 'pop' => 1],
            2 => ['wood' => 100, 'clay' => 130, 'iron' => 160, 'crop' => 70, 'time' => 1320, 'pop' => 1],
            3 => ['wood' => 150, 'clay' => 160, 'iron' => 210, 'crop' => 80, 'time' => 1800, 'pop' => 2],
            4 => ['wood' => 140, 'clay' => 160, 'iron' => 20, 'crop' => 40, 'time' => 1040, 'pop' => 1],
            5 => ['wood' => 550, 'clay' => 440, 'iron' => 320, 'crop' => 100, 'time' => 2800, 'pop' => 3],
            6 => ['wood' => 450, 'clay' => 510, 'iron' => 610, 'crop' => 180, 'time' => 3400, 'pop' => 4],
            7 => ['wood' => 950, 'clay' => 555, 'iron' => 330, 'crop' => 75, 'time' => 4200, 'pop' => 5],
            8 => ['wood' => 960, 'clay' => 1450, 'iron' => 630, 'crop' => 90, 'time' => 5000, 'pop' => 6],
            9 => ['wood' => 30, 'clay' => 10, 'iron' => 20, 'crop' => 0, 'time' => 1400, 'pop' => 0],
            10 => ['wood' => 3000, 'clay' => 3000, 'iron' => 3000, 'crop' => 6000, 'time' => 18000, 'pop' => 5],
            11 => ['wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0, 'time' => 0, 'pop' => 6]
        ];

        if (!isset($baseCosts[$unitType])) {
            $this->response = ['error' => 'Invalid unit type'];
            return;
        }

        $cost = $baseCosts[$unitType];
        $totalWood = $cost['wood'] * $quantity;
        $totalClay = $cost['clay'] * $quantity;
        $totalIron = $cost['iron'] * $quantity;
        $totalCrop = $cost['crop'] * $quantity;

        if ($village['wood'] < $totalWood || $village['clay'] < $totalClay || 
            $village['iron'] < $totalIron || $village['crop'] < $totalCrop) {
            $this->response = [
                'error' => 'Insufficient resources',
                'required' => [
                    'wood' => $totalWood,
                    'clay' => $totalClay,
                    'iron' => $totalIron,
                    'crop' => $totalCrop
                ],
                'available' => [
                    'wood' => (int)$village['wood'],
                    'clay' => (int)$village['clay'],
                    'iron' => (int)$village['iron'],
                    'crop' => (int)$village['crop']
                ]
            ];
            return;
        }

        $speed = $server['speed'] ?? 1;
        $duration = ($cost['time'] * $quantity) / $speed;

        $this->response = [
            'success' => true,
            'message' => 'Training queued (simulation mode)',
            'cost' => [
                'wood' => $totalWood,
                'clay' => $totalClay,
                'iron' => $totalIron,
                'crop' => $totalCrop
            ],
            'duration' => (int)$duration,
            'quantity' => $quantity,
            'unitType' => $unitType,
            'queuePosition' => 1
        ];
    }

    public function getTrainingQueue()
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

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'training')");
        $tableExists = $tableCheck->fetchColumn();

        if (!$tableExists) {
            $this->response = [
                'queues' => [],
                'message' => 'Training queue table not available'
            ];
            return;
        }

        $stmt = $serverDB->prepare("SELECT * FROM training WHERE kid = :kid AND end_time > :now ORDER BY end_time ASC");
        $stmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
        $stmt->bindValue('now', time(), PDO::PARAM_INT);
        $stmt->execute();

        $queues = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $timeRemaining = (int)$row['end_time'] - time();
            $queues[] = [
                'queueId' => (int)$row['id'],
                'building' => $row['building'] ?? 'barracks',
                'unitType' => (int)($row['unit_type'] ?? 0),
                'quantity' => (int)($row['quantity'] ?? 0),
                'timeRemaining' => max(0, $timeRemaining),
                'startTime' => (int)($row['start_time'] ?? 0),
                'endTime' => (int)$row['end_time']
            ];
        }

        $this->response = [
            'queues' => $queues,
            'totalInQueue' => count($queues)
        ];
    }

    public function sendAttack()
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
        if (!isset($this->payload['troops'])) {
            throw new MissingParameterException('troops');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $fromKid = (int)$this->payload['fromVillageId'];
        $toKid = $this->coordsToKid((int)$this->payload['toX'], (int)$this->payload['toY']);
        $troops = $this->payload['troops'];
        $attackType = $this->payload['attackType'] ?? 'normal';

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $fromVillageStmt = $serverDB->prepare("SELECT * FROM vdata WHERE kid = :kid");
        $fromVillageStmt->bindValue('kid', $fromKid, PDO::PARAM_INT);
        $fromVillageStmt->execute();

        if (!$fromVillageStmt->rowCount()) {
            $this->response = ['error' => 'Source village not found'];
            return;
        }

        $unitsStmt = $serverDB->prepare("SELECT * FROM units WHERE kid = :kid");
        $unitsStmt->bindValue('kid', $fromKid, PDO::PARAM_INT);
        $unitsStmt->execute();

        if (!$unitsStmt->rowCount()) {
            $this->response = ['error' => 'No troops available'];
            return;
        }

        $available = $unitsStmt->fetch(PDO::FETCH_ASSOC);
        
        foreach ($troops as $unitType => $count) {
            $unitNum = str_replace('u', '', $unitType);
            if ((int)$count > (int)($available["u{$unitNum}"] ?? 0)) {
                $this->response = [
                    'error' => 'Insufficient troops',
                    'unitType' => $unitType,
                    'requested' => (int)$count,
                    'available' => (int)($available["u{$unitNum}"] ?? 0)
                ];
                return;
            }
        }

        $fromCoords = $this->kidToCoords($fromKid);
        $toCoords = ['x' => (int)$this->payload['toX'], 'y' => (int)$this->payload['toY']];
        $distance = $this->calculateDistance($fromCoords['x'], $fromCoords['y'], $toCoords['x'], $toCoords['y']);

        $baseSpeed = 6;
        $worldSpeed = $server['speed'] ?? 1;
        $travelTime = ($distance * 3600) / ($baseSpeed * $worldSpeed);
        $arrivalTime = time() + (int)$travelTime;

        $this->response = [
            'success' => true,
            'message' => 'Attack sent (simulation mode)',
            'movementId' => rand(1000, 9999),
            'distance' => round($distance, 2),
            'travelTime' => (int)$travelTime,
            'arrivalTime' => $arrivalTime,
            'attackType' => $attackType,
            'from' => $fromCoords,
            'to' => $toCoords
        ];
    }

    public function sendReinforcement()
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
        if (!isset($this->payload['troops'])) {
            throw new MissingParameterException('troops');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $fromKid = (int)$this->payload['fromVillageId'];
        $toKid = $this->coordsToKid((int)$this->payload['toX'], (int)$this->payload['toY']);
        $troops = $this->payload['troops'];

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $unitsStmt = $serverDB->prepare("SELECT * FROM units WHERE kid = :kid");
        $unitsStmt->bindValue('kid', $fromKid, PDO::PARAM_INT);
        $unitsStmt->execute();

        if (!$unitsStmt->rowCount()) {
            $this->response = ['error' => 'No troops available'];
            return;
        }

        $available = $unitsStmt->fetch(PDO::FETCH_ASSOC);
        
        foreach ($troops as $unitType => $count) {
            $unitNum = str_replace('u', '', $unitType);
            if ((int)$count > (int)($available["u{$unitNum}"] ?? 0)) {
                $this->response = [
                    'error' => 'Insufficient troops',
                    'unitType' => $unitType,
                    'requested' => (int)$count,
                    'available' => (int)($available["u{$unitNum}"] ?? 0)
                ];
                return;
            }
        }

        $fromCoords = $this->kidToCoords($fromKid);
        $toCoords = ['x' => (int)$this->payload['toX'], 'y' => (int)$this->payload['toY']];
        $distance = $this->calculateDistance($fromCoords['x'], $fromCoords['y'], $toCoords['x'], $toCoords['y']);

        $baseSpeed = 6;
        $worldSpeed = $server['speed'] ?? 1;
        $travelTime = ($distance * 3600) / ($baseSpeed * $worldSpeed);
        $arrivalTime = time() + (int)$travelTime;

        $this->response = [
            'success' => true,
            'message' => 'Reinforcement sent (simulation mode)',
            'movementId' => rand(1000, 9999),
            'distance' => round($distance, 2),
            'travelTime' => (int)$travelTime,
            'arrivalTime' => $arrivalTime,
            'from' => $fromCoords,
            'to' => $toCoords
        ];
    }

    public function getMovements()
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

        $type = $this->payload['type'] ?? 'all';
        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'movement')");
        $tableExists = $tableCheck->fetchColumn();

        if (!$tableExists) {
            $this->response = [
                'movements' => [],
                'message' => 'Movement table not available'
            ];
            return;
        }

        $movements = [];
        $kid = (int)$this->payload['villageId'];

        if ($type === 'all' || $type === 'outgoing') {
            $outgoingStmt = $serverDB->prepare("
                SELECT * FROM movement 
                WHERE from_kid = :kid AND end_time > :now 
                ORDER BY end_time ASC
            ");
            $outgoingStmt->bindValue('kid', $kid, PDO::PARAM_INT);
            $outgoingStmt->bindValue('now', time(), PDO::PARAM_INT);
            $outgoingStmt->execute();

            while ($row = $outgoingStmt->fetch(PDO::FETCH_ASSOC)) {
                $movements[] = $this->formatMovement($row, 'outgoing');
            }
        }

        if ($type === 'all' || $type === 'incoming') {
            $incomingStmt = $serverDB->prepare("
                SELECT * FROM movement 
                WHERE to_kid = :kid AND end_time > :now 
                ORDER BY end_time ASC
            ");
            $incomingStmt->bindValue('kid', $kid, PDO::PARAM_INT);
            $incomingStmt->bindValue('now', time(), PDO::PARAM_INT);
            $incomingStmt->execute();

            while ($row = $incomingStmt->fetch(PDO::FETCH_ASSOC)) {
                $movements[] = $this->formatMovement($row, 'incoming');
            }
        }

        $this->response = [
            'movements' => $movements,
            'total' => count($movements),
            'filterType' => $type
        ];
    }

    private function formatMovement($row, $direction)
    {
        $timeRemaining = (int)$row['end_time'] - time();
        $fromCoords = $this->kidToCoords((int)$row['from_kid']);
        $toCoords = $this->kidToCoords((int)$row['to_kid']);

        return [
            'id' => (int)$row['id'],
            'direction' => $direction,
            'from' => $fromCoords,
            'to' => $toCoords,
            'type' => $row['type'] ?? 'attack',
            'arrivalTime' => (int)$row['end_time'],
            'timeRemaining' => max(0, $timeRemaining),
            'startTime' => (int)($row['start_time'] ?? 0)
        ];
    }

    private function kidToCoords($kid)
    {
        $y = floor($kid / 801) - 200;
        $x = ($kid % 801) - 200;
        return ['x' => (int)$x, 'y' => (int)$y];
    }

    private function coordsToKid($x, $y)
    {
        return ($y + 200) * 801 + ($x + 200);
    }

    private function calculateDistance($x1, $y1, $x2, $y2)
    {
        return sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
    }
}

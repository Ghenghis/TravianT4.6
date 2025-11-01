<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use PDO;

class VillageCtrl extends ApiAbstractCtrl
{
    public function getVillageList()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            $this->response = ['error' => 'Invalid world ID'];
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $stmt = $serverDB->prepare("SELECT * FROM vdata WHERE owner=:uid ORDER BY capital DESC, kid ASC");
        $stmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $stmt->execute();

        $villages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $coords = $this->kidToCoords($row['kid']);
            $villages[] = [
                'villageId' => (int)$row['kid'],
                'name' => $row['name'],
                'coordinates' => $coords,
                'population' => (int)$row['pop'],
                'isCapital' => (int)$row['capital'] === 1,
                'resources' => [
                    'wood' => (int)$row['wood'],
                    'clay' => (int)$row['clay'],
                    'iron' => (int)$row['iron'],
                    'crop' => (int)$row['crop']
                ],
                'storage' => [
                    'maxWarehouse' => (int)$row['maxstore'],
                    'maxGranary' => (int)$row['maxcrop']
                ]
            ];
        }

        $this->response = [
            'villages' => $villages,
            'totalVillages' => count($villages)
        ];
    }

    public function getVillageDetails()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['villageId'])) {
            throw new MissingParameterException('villageId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            $this->response = ['error' => 'Invalid world ID'];
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        
        $stmt = $serverDB->prepare("SELECT v.*, u.name as ownerName, u.race FROM vdata v LEFT JOIN users u ON v.owner=u.id WHERE v.kid=:kid");
        $stmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
        $stmt->execute();

        if (!$stmt->rowCount()) {
            $this->response = ['error' => 'Village not found'];
            return;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $coords = $this->kidToCoords($row['kid']);

        $buildingsStmt = $serverDB->prepare("SELECT * FROM fdata WHERE kid=:kid");
        $buildingsStmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
        $buildingsStmt->execute();
        $buildings = $buildingsStmt->fetch(PDO::FETCH_ASSOC);

        $this->response = [
            'village' => [
                'villageId' => (int)$row['kid'],
                'name' => $row['name'],
                'coordinates' => $coords,
                'population' => (int)$row['pop'],
                'owner' => [
                    'userId' => (int)$row['owner'],
                    'name' => $row['ownerName'],
                    'race' => (int)$row['race']
                ],
                'isCapital' => (int)$row['capital'] === 1,
                'celebrationPoints' => (int)$row['cp'],
                'loyalty' => (int)$row['loyalty'],
                'resources' => [
                    'wood' => (int)$row['wood'],
                    'clay' => (int)$row['clay'],
                    'iron' => (int)$row['iron'],
                    'crop' => (int)$row['crop']
                ],
                'production' => [
                    'wood' => (int)$row['woodp'],
                    'clay' => (int)$row['clayp'],
                    'iron' => (int)$row['ironp'],
                    'crop' => (int)$row['cropp']
                ],
                'storage' => [
                    'maxWarehouse' => (int)$row['maxstore'],
                    'maxGranary' => (int)$row['maxcrop']
                ],
                'upkeep' => (int)$row['upkeep'],
                'buildings' => $this->formatBuildings($buildings)
            ]
        ];
    }

    public function getResources()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['villageId'])) {
            throw new MissingParameterException('villageId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            $this->response = ['error' => 'Invalid world ID'];
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $stmt = $serverDB->prepare("SELECT wood, clay, iron, crop, woodp, clayp, ironp, cropp, maxstore, maxcrop, lastmupdate FROM vdata WHERE kid=:kid");
        $stmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
        $stmt->execute();

        if (!$stmt->rowCount()) {
            $this->response = ['error' => 'Village not found'];
            return;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $timeSinceUpdate = time() - $row['lastmupdate'];
        $currentWood = min((int)$row['wood'] + ($row['woodp'] * $timeSinceUpdate / 3600), (int)$row['maxstore']);
        $currentClay = min((int)$row['clay'] + ($row['clayp'] * $timeSinceUpdate / 3600), (int)$row['maxstore']);
        $currentIron = min((int)$row['iron'] + ($row['ironp'] * $timeSinceUpdate / 3600), (int)$row['maxstore']);
        $currentCrop = min((int)$row['crop'] + ($row['cropp'] * $timeSinceUpdate / 3600), (int)$row['maxcrop']);

        $this->response = [
            'resources' => [
                'wood' => [
                    'current' => (int)$currentWood,
                    'production' => (int)$row['woodp'],
                    'max' => (int)$row['maxstore']
                ],
                'clay' => [
                    'current' => (int)$currentClay,
                    'production' => (int)$row['clayp'],
                    'max' => (int)$row['maxstore']
                ],
                'iron' => [
                    'current' => (int)$currentIron,
                    'production' => (int)$row['ironp'],
                    'max' => (int)$row['maxstore']
                ],
                'crop' => [
                    'current' => (int)$currentCrop,
                    'production' => (int)$row['cropp'],
                    'max' => (int)$row['maxcrop']
                ]
            ],
            'lastUpdate' => (int)$row['lastmupdate'],
            'serverTime' => time()
        ];
    }

    public function getBuildingQueue()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['villageId'])) {
            throw new MissingParameterException('villageId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            $this->response = ['error' => 'Invalid world ID'];
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        
        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'building')");
        $tableExists = $tableCheck->fetchColumn();

        if (!$tableExists) {
            $this->response = [
                'queue' => [],
                'message' => 'Building queue table not available'
            ];
            return;
        }

        $stmt = $serverDB->prepare("SELECT * FROM building WHERE kid=:kid ORDER BY timestamp ASC");
        $stmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
        $stmt->execute();

        $queue = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $queue[] = [
                'queueId' => (int)$row['id'],
                'buildingSlot' => (int)$row['field'],
                'buildingType' => (int)$row['type'],
                'targetLevel' => (int)$row['level'],
                'startTime' => (int)$row['timestamp'],
                'finishTime' => (int)$row['timestamp'] + (int)$row['master']
            ];
        }

        $this->response = [
            'queue' => $queue,
            'totalInQueue' => count($queue)
        ];
    }

    public function upgradeBuilding()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['villageId'])) {
            throw new MissingParameterException('villageId');
        }
        if (!isset($this->payload['buildingSlot'])) {
            throw new MissingParameterException('buildingSlot');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            $this->response = ['error' => 'Invalid world ID'];
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        
        $stmt = $serverDB->prepare("SELECT * FROM fdata WHERE kid=:kid");
        $stmt->bindValue('kid', $this->payload['villageId'], PDO::PARAM_INT);
        $stmt->execute();

        if (!$stmt->rowCount()) {
            $this->response = ['error' => 'Village not found'];
            return;
        }

        $buildings = $stmt->fetch(PDO::FETCH_ASSOC);
        $slot = $this->payload['buildingSlot'];
        $currentLevel = isset($buildings["f{$slot}"]) ? (int)$buildings["f{$slot}"] : 0;
        $buildingType = isset($buildings["f{$slot}t"]) ? (int)$buildings["f{$slot}t"] : 0;

        if ($currentLevel >= 20) {
            $this->response = ['error' => 'Building already at max level'];
            return;
        }

        $this->response = [
            'success' => true,
            'message' => 'Building upgrade queued (simulation mode)',
            'buildingSlot' => (int)$slot,
            'currentLevel' => $currentLevel,
            'targetLevel' => $currentLevel + 1,
            'buildingType' => $buildingType
        ];
    }

    private function kidToCoords($kid)
    {
        $y = floor($kid / 801) - 200;
        $x = ($kid % 801) - 200;
        return ['x' => (int)$x, 'y' => (int)$y];
    }

    private function formatBuildings($buildings)
    {
        if (!$buildings) return [];
        
        $formatted = [];
        for ($i = 1; $i <= 40; $i++) {
            $level = isset($buildings["f{$i}"]) ? (int)$buildings["f{$i}"] : 0;
            $type = isset($buildings["f{$i}t"]) ? (int)$buildings["f{$i}t"] : 0;
            
            if ($level > 0 || $type > 0) {
                $formatted[] = [
                    'slot' => $i,
                    'type' => $type,
                    'level' => $level,
                    'isResourceField' => $i <= 18
                ];
            }
        }
        return $formatted;
    }
}

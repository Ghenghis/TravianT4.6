<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use Exceptions\NotFoundException;
use PDO;

class MapCtrl extends ApiAbstractCtrl
{
    public function getMapData()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['x1']) || !isset($this->payload['y1'])) {
            throw new MissingParameterException('x1, y1');
        }
        if (!isset($this->payload['x2']) || !isset($this->payload['y2'])) {
            throw new MissingParameterException('x2, y2');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $x1 = (int)$this->payload['x1'];
        $y1 = (int)$this->payload['y1'];
        $x2 = (int)$this->payload['x2'];
        $y2 = (int)$this->payload['y2'];
        
        $rangeX = abs($x2 - $x1);
        $rangeY = abs($y2 - $y1);
        if ($rangeX > 40 || $rangeY > 40) {
            $this->response = ['error' => 'Map range exceeds maximum 40x40 tiles'];
            return;
        }

        $limit = isset($this->payload['limit']) ? min((int)$this->payload['limit'], 1600) : 400;
        $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        
        $minKid = $this->coordsToKid(min($x1, $x2), min($y1, $y2));
        $maxKid = $this->coordsToKid(max($x1, $x2), max($y1, $y2));

        $stmt = $serverDB->prepare("
            SELECT v.kid, v.owner, v.name, v.pop, u.name as owner_name, u.alliance_id, a.tag as alliance_tag
            FROM vdata v
            LEFT JOIN users u ON v.owner = u.id
            LEFT JOIN alliance a ON u.alliance_id = a.id
            WHERE v.kid >= :minKid AND v.kid <= :maxKid
            ORDER BY v.kid
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('minKid', $minKid, PDO::PARAM_INT);
        $stmt->bindValue('maxKid', $maxKid, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $tiles = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $coords = $this->kidToCoords($row['kid']);
            $tiles[] = [
                'x' => $coords['x'],
                'y' => $coords['y'],
                'type' => 'village',
                'villageId' => (int)$row['kid'],
                'villageName' => $row['name'],
                'owner' => [
                    'userId' => (int)$row['owner'],
                    'name' => $row['owner_name']
                ],
                'alliance' => $row['alliance_id'] ? [
                    'id' => (int)$row['alliance_id'],
                    'tag' => $row['alliance_tag']
                ] : null,
                'population' => (int)$row['pop']
            ];
        }

        $countStmt = $serverDB->prepare("
            SELECT COUNT(*) FROM vdata WHERE kid >= :minKid AND kid <= :maxKid
        ");
        $countStmt->bindValue('minKid', $minKid, PDO::PARAM_INT);
        $countStmt->bindValue('maxKid', $maxKid, PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $this->response = [
            'tiles' => $tiles,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total,
            'rangeRequested' => [
                'x1' => $x1,
                'y1' => $y1,
                'x2' => $x2,
                'y2' => $y2
            ]
        ];
    }

    public function getVillageInfo()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['x']) || !isset($this->payload['y'])) {
            throw new MissingParameterException('x, y');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $kid = $this->coordsToKid((int)$this->payload['x'], (int)$this->payload['y']);
        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $stmt = $serverDB->prepare("
            SELECT v.kid, v.owner, v.name, v.pop, u.name as owner_name, u.race, u.alliance_id, a.name as alliance_name, a.tag as alliance_tag
            FROM vdata v
            LEFT JOIN users u ON v.owner = u.id
            LEFT JOIN alliance a ON u.alliance_id = a.id
            WHERE v.kid = :kid
        ");
        $stmt->bindValue('kid', $kid, PDO::PARAM_INT);
        $stmt->execute();

        if (!$stmt->rowCount()) {
            $this->response = ['village' => null];
            return;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $coords = $this->kidToCoords($row['kid']);

        $this->response = [
            'village' => [
                'id' => (int)$row['kid'],
                'name' => $row['name'],
                'coordinates' => $coords,
                'owner' => [
                    'userId' => (int)$row['owner'],
                    'name' => $row['owner_name'],
                    'tribe' => (int)$row['race']
                ],
                'alliance' => $row['alliance_id'] ? [
                    'id' => (int)$row['alliance_id'],
                    'name' => $row['alliance_name'],
                    'tag' => $row['alliance_tag']
                ] : null,
                'population' => (int)$row['pop']
            ]
        ];
    }

    public function getTileDetails()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['x']) || !isset($this->payload['y'])) {
            throw new MissingParameterException('x, y');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $kid = $this->coordsToKid((int)$this->payload['x'], (int)$this->payload['y']);
        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $villageStmt = $serverDB->prepare("SELECT kid, owner, name FROM vdata WHERE kid = :kid");
        $villageStmt->bindValue('kid', $kid, PDO::PARAM_INT);
        $villageStmt->execute();

        if ($villageStmt->rowCount()) {
            $village = $villageStmt->fetch(PDO::FETCH_ASSOC);
            $this->response = [
                'tile' => [
                    'type' => 'village',
                    'coordinates' => $this->kidToCoords($kid),
                    'village' => [
                        'id' => (int)$village['kid'],
                        'name' => $village['name'],
                        'ownerId' => (int)$village['owner']
                    ]
                ]
            ];
            return;
        }

        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'odata')");
        $tableExists = $tableCheck->fetchColumn();

        if ($tableExists) {
            $oasisStmt = $serverDB->prepare("SELECT * FROM odata WHERE kid = :kid");
            $oasisStmt->bindValue('kid', $kid, PDO::PARAM_INT);
            $oasisStmt->execute();

            if ($oasisStmt->rowCount()) {
                $oasis = $oasisStmt->fetch(PDO::FETCH_ASSOC);
                $this->response = [
                    'tile' => [
                        'type' => 'oasis',
                        'coordinates' => $this->kidToCoords($kid),
                        'oasis' => [
                            'type' => (int)($oasis['type'] ?? 0),
                            'bonuses' => $oasis['bonuses'] ?? null,
                            'owner' => isset($oasis['owner']) && $oasis['owner'] > 0 ? (int)$oasis['owner'] : null
                        ]
                    ]
                ];
                return;
            }
        }

        $this->response = [
            'tile' => [
                'type' => 'empty',
                'coordinates' => $this->kidToCoords($kid)
            ]
        ];
    }

    public function searchVillages()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['query'])) {
            throw new MissingParameterException('query');
        }

        $query = trim($this->payload['query']);
        if (strlen($query) < 3) {
            $this->response = ['error' => 'Search query must be at least 3 characters'];
            return;
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $type = $this->payload['type'] ?? 'village';
        $limit = isset($this->payload['limit']) ? min((int)$this->payload['limit'], 50) : 20;
        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $searchPattern = '%' . $query . '%';
        $results = [];

        if ($type === 'village') {
            $stmt = $serverDB->prepare("
                SELECT v.kid, v.name, v.owner, v.pop, u.name as owner_name
                FROM vdata v
                LEFT JOIN users u ON v.owner = u.id
                WHERE v.name ILIKE :query
                ORDER BY v.pop DESC
                LIMIT :limit
            ");
            $stmt->bindValue('query', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $coords = $this->kidToCoords($row['kid']);
                $results[] = [
                    'id' => (int)$row['kid'],
                    'name' => $row['name'],
                    'type' => 'village',
                    'owner' => $row['owner_name'],
                    'coordinates' => $coords,
                    'population' => (int)$row['pop']
                ];
            }
        } elseif ($type === 'player') {
            $stmt = $serverDB->prepare("
                SELECT id, name, total_pop, total_villages
                FROM users
                WHERE name ILIKE :query
                ORDER BY total_pop DESC
                LIMIT :limit
            ");
            $stmt->bindValue('query', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'type' => 'player',
                    'population' => (int)$row['total_pop'],
                    'villages' => (int)$row['total_villages']
                ];
            }
        } elseif ($type === 'alliance') {
            $stmt = $serverDB->prepare("
                SELECT id, name, tag
                FROM alliance
                WHERE name ILIKE :query OR tag ILIKE :query
                ORDER BY id ASC
                LIMIT :limit
            ");
            $stmt->bindValue('query', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'tag' => $row['tag'],
                    'type' => 'alliance'
                ];
            }
        }

        $this->response = [
            'results' => $results,
            'total' => count($results),
            'query' => $query,
            'searchType' => $type
        ];
    }

    public function getNearby()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['x']) || !isset($this->payload['y'])) {
            throw new MissingParameterException('x, y');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $centerX = (int)$this->payload['x'];
        $centerY = (int)$this->payload['y'];
        $radius = isset($this->payload['radius']) ? min((int)$this->payload['radius'], 20) : 10;

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $minKid = $this->coordsToKid($centerX - $radius, $centerY - $radius);
        $maxKid = $this->coordsToKid($centerX + $radius, $centerY + $radius);

        $stmt = $serverDB->prepare("
            SELECT v.kid, v.owner, v.name, v.pop, u.name as owner_name
            FROM vdata v
            LEFT JOIN users u ON v.owner = u.id
            WHERE v.kid >= :minKid AND v.kid <= :maxKid
            ORDER BY v.kid
        ");
        $stmt->bindValue('minKid', $minKid, PDO::PARAM_INT);
        $stmt->bindValue('maxKid', $maxKid, PDO::PARAM_INT);
        $stmt->execute();

        $villages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $coords = $this->kidToCoords($row['kid']);
            $distance = $this->calculateDistance($centerX, $centerY, $coords['x'], $coords['y']);
            
            if ($distance <= $radius) {
                $villages[] = [
                    'id' => (int)$row['kid'],
                    'name' => $row['name'],
                    'coordinates' => $coords,
                    'owner' => [
                        'userId' => (int)$row['owner'],
                        'name' => $row['owner_name']
                    ],
                    'distance' => round($distance, 2),
                    'population' => (int)$row['pop']
                ];
            }
        }

        usort($villages, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        $this->response = [
            'villages' => $villages,
            'total' => count($villages),
            'center' => ['x' => $centerX, 'y' => $centerY],
            'radius' => $radius
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

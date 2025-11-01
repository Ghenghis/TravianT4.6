<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use Exceptions\NotFoundException;
use PDO;

class AllianceCtrl extends ApiAbstractCtrl
{
    public function create()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['name'])) {
            throw new MissingParameterException('name');
        }
        if (!isset($this->payload['tag'])) {
            throw new MissingParameterException('tag');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $name = trim($this->payload['name']);
        $tag = trim($this->payload['tag']);
        $description = isset($this->payload['description']) ? trim($this->payload['description']) : '';

        if (strlen($name) < 3 || strlen($name) > 50) {
            $this->response = ['error' => 'Alliance name must be 3-50 characters'];
            return;
        }

        if (strlen($tag) < 2 || strlen($tag) > 6) {
            $this->response = ['error' => 'Alliance tag must be 2-6 characters'];
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $userStmt = $serverDB->prepare("SELECT alliance_id FROM users WHERE id = :uid");
        $userStmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $userStmt->execute();

        if (!$userStmt->rowCount()) {
            $this->response = ['error' => 'User not found'];
            return;
        }

        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($user['alliance_id'] > 0) {
            $this->response = ['error' => 'User already in alliance'];
            return;
        }

        $nameCheckStmt = $serverDB->prepare("SELECT id FROM alliance WHERE LOWER(name) = LOWER(:name) OR LOWER(tag) = LOWER(:tag)");
        $nameCheckStmt->bindValue('name', $name, PDO::PARAM_STR);
        $nameCheckStmt->bindValue('tag', $tag, PDO::PARAM_STR);
        $nameCheckStmt->execute();

        if ($nameCheckStmt->rowCount()) {
            $this->response = ['error' => 'Alliance name or tag already exists'];
            return;
        }

        $this->response = [
            'success' => true,
            'message' => 'Alliance creation simulated (requires embassy level 3)',
            'allianceId' => rand(1, 10000),
            'name' => $name,
            'tag' => $tag,
            'description' => $description,
            'founder' => (int)$this->payload['uid']
        ];
    }

    public function invite()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['allianceId'])) {
            throw new MissingParameterException('allianceId');
        }
        if (!isset($this->payload['targetUid'])) {
            throw new MissingParameterException('targetUid');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $allianceStmt = $serverDB->prepare("SELECT * FROM alliance WHERE id = :id");
        $allianceStmt->bindValue('id', $this->payload['allianceId'], PDO::PARAM_INT);
        $allianceStmt->execute();

        if (!$allianceStmt->rowCount()) {
            $this->response = ['error' => 'Alliance not found'];
            return;
        }

        $targetStmt = $serverDB->prepare("SELECT id, name, alliance_id FROM users WHERE id = :uid");
        $targetStmt->bindValue('uid', $this->payload['targetUid'], PDO::PARAM_INT);
        $targetStmt->execute();

        if (!$targetStmt->rowCount()) {
            $this->response = ['error' => 'Target player not found'];
            return;
        }

        $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if ($target['alliance_id'] > 0) {
            $this->response = ['error' => 'Player already in alliance'];
            return;
        }

        $this->response = [
            'success' => true,
            'message' => 'Invitation sent (simulation mode)',
            'inviteId' => rand(1000, 9999),
            'targetPlayer' => $target['name'],
            'targetUid' => (int)$target['id']
        ];
    }

    public function getMembers()
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

        $allianceStmt = $serverDB->prepare("SELECT * FROM alliance WHERE id = :id");
        $allianceStmt->bindValue('id', $this->payload['allianceId'], PDO::PARAM_INT);
        $allianceStmt->execute();

        if (!$allianceStmt->rowCount()) {
            $this->response = ['error' => 'Alliance not found'];
            return;
        }

        $alliance = $allianceStmt->fetch(PDO::FETCH_ASSOC);

        $membersStmt = $serverDB->prepare("
            SELECT u.id, u.name, u.total_pop, u.total_villages, u.race,
                   COUNT(v.kid) as village_count,
                   SUM(v.pop) as total_population
            FROM users u
            LEFT JOIN vdata v ON u.id = v.owner
            WHERE u.alliance_id = :allianceId
            GROUP BY u.id, u.name, u.total_pop, u.total_villages, u.race
            ORDER BY total_population DESC
        ");
        $membersStmt->bindValue('allianceId', $this->payload['allianceId'], PDO::PARAM_INT);
        $membersStmt->execute();

        $members = [];
        $rank = 1;
        while ($row = $membersStmt->fetch(PDO::FETCH_ASSOC)) {
            $members[] = [
                'uid' => (int)$row['id'],
                'name' => $row['name'],
                'rank' => $rank++,
                'villages' => (int)$row['village_count'],
                'population' => (int)$row['total_population'],
                'tribe' => (int)$row['race'],
                'role' => 'member'
            ];
        }

        $this->response = [
            'alliance' => [
                'id' => (int)$alliance['id'],
                'name' => $alliance['name'],
                'tag' => $alliance['tag']
            ],
            'members' => $members,
            'totalMembers' => count($members)
        ];
    }

    public function setDiplomacy()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['allianceId'])) {
            throw new MissingParameterException('allianceId');
        }
        if (!isset($this->payload['targetAllianceId'])) {
            throw new MissingParameterException('targetAllianceId');
        }
        if (!isset($this->payload['status'])) {
            throw new MissingParameterException('status');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $validStatuses = ['war', 'peace', 'nap', 'confederation'];
        $status = strtolower($this->payload['status']);

        if (!in_array($status, $validStatuses)) {
            $this->response = ['error' => 'Invalid status. Must be: war, peace, nap, or confederation'];
            return;
        }

        if ($this->payload['allianceId'] == $this->payload['targetAllianceId']) {
            $this->response = ['error' => 'Cannot set diplomacy with own alliance'];
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);

        $allianceStmt = $serverDB->prepare("SELECT * FROM alliance WHERE id = :id");
        $allianceStmt->bindValue('id', $this->payload['allianceId'], PDO::PARAM_INT);
        $allianceStmt->execute();

        if (!$allianceStmt->rowCount()) {
            $this->response = ['error' => 'Alliance not found'];
            return;
        }

        $targetStmt = $serverDB->prepare("SELECT * FROM alliance WHERE id = :id");
        $targetStmt->bindValue('id', $this->payload['targetAllianceId'], PDO::PARAM_INT);
        $targetStmt->execute();

        if (!$targetStmt->rowCount()) {
            $this->response = ['error' => 'Target alliance not found'];
            return;
        }

        $target = $targetStmt->fetch(PDO::FETCH_ASSOC);

        $this->response = [
            'success' => true,
            'message' => 'Diplomacy set (simulation mode)',
            'diplomacy' => [
                'allianceId' => (int)$this->payload['allianceId'],
                'targetAllianceId' => (int)$this->payload['targetAllianceId'],
                'targetAllianceName' => $target['name'],
                'status' => $status,
                'since' => time()
            ]
        ];
    }

    public function getDiplomacy()
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

        $allianceStmt = $serverDB->prepare("SELECT * FROM alliance WHERE id = :id");
        $allianceStmt->bindValue('id', $this->payload['allianceId'], PDO::PARAM_INT);
        $allianceStmt->execute();

        if (!$allianceStmt->rowCount()) {
            $this->response = ['error' => 'Alliance not found'];
            return;
        }

        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'diplomacy')");
        $tableExists = $tableCheck->fetchColumn();

        $diplomacy = [];

        if ($tableExists) {
            $dipStmt = $serverDB->prepare("
                SELECT d.*, a.name, a.tag
                FROM diplomacy d
                LEFT JOIN alliance a ON d.target_alliance_id = a.id
                WHERE d.alliance_id = :allianceId
                ORDER BY d.created_at DESC
            ");
            $dipStmt->bindValue('allianceId', $this->payload['allianceId'], PDO::PARAM_INT);
            $dipStmt->execute();

            while ($row = $dipStmt->fetch(PDO::FETCH_ASSOC)) {
                $diplomacy[] = [
                    'allianceId' => (int)$row['target_alliance_id'],
                    'name' => $row['name'],
                    'tag' => $row['tag'],
                    'status' => $row['status'],
                    'since' => (int)($row['created_at'] ?? time())
                ];
            }
        }

        $this->response = [
            'diplomacy' => $diplomacy,
            'total' => count($diplomacy),
            'message' => $tableExists ? null : 'Diplomacy table not available'
        ];
    }
}

<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use Exceptions\NotFoundException;
use PDO;

class QuestCtrl extends ApiAbstractCtrl
{
    public function getActiveQuests()
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
        $this->ensureQuestTablesExist($serverDB);

        $stmt = $serverDB->prepare("
            SELECT qp.*, qr.title, qr.description, qr.quest_type, qr.gold, qr.wood, qr.clay, qr.iron, qr.crop, qr.xp
            FROM quest_progress qp
            JOIN quest_rewards qr ON qp.quest_id = qr.quest_id
            WHERE qp.uid=:uid AND qp.completed=false
            ORDER BY qp.quest_id ASC
        ");
        $stmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $stmt->execute();

        $quests = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $quests[] = [
                'questId' => (int)$row['quest_id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'type' => $row['quest_type'],
                'progress' => [
                    'current' => (int)$row['progress'],
                    'required' => (int)$row['required'],
                    'percentage' => round(((int)$row['progress'] / (int)$row['required']) * 100, 2)
                ],
                'rewards' => [
                    'gold' => (int)$row['gold'],
                    'resources' => [
                        'wood' => (int)$row['wood'],
                        'clay' => (int)$row['clay'],
                        'iron' => (int)$row['iron'],
                        'crop' => (int)$row['crop']
                    ],
                    'experience' => (int)$row['xp']
                ],
                'canComplete' => (int)$row['progress'] >= (int)$row['required']
            ];
        }

        $this->response = [
            'quests' => $quests,
            'total' => count($quests)
        ];
    }

    public function completeQuest()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['questId'])) {
            throw new MissingParameterException('questId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureQuestTablesExist($serverDB);

        $stmt = $serverDB->prepare("SELECT * FROM quest_progress WHERE uid=:uid AND quest_id=:qid");
        $stmt->execute([
            'uid' => $this->payload['uid'],
            'qid' => $this->payload['questId']
        ]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$progress) {
            $this->response = ['error' => 'Quest not found'];
            return;
        }

        if ((int)$progress['progress'] < (int)$progress['required']) {
            $this->response = ['error' => 'Quest requirements not met'];
            return;
        }

        if ($progress['rewarded']) {
            $this->response = ['error' => 'Quest already completed'];
            return;
        }

        $rewardStmt = $serverDB->prepare("SELECT * FROM quest_rewards WHERE quest_id=:qid");
        $rewardStmt->execute(['qid' => $this->payload['questId']]);
        $rewards = $rewardStmt->fetch(PDO::FETCH_ASSOC);

        if ($rewards) {
            $userStmt = $serverDB->prepare("SELECT * FROM users WHERE id=:uid");
            $userStmt->execute(['uid' => $this->payload['uid']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($user && isset($user['gold'])) {
                $serverDB->prepare("UPDATE users SET gold=gold+:gold WHERE id=:uid")
                          ->execute(['gold' => $rewards['gold'], 'uid' => $this->payload['uid']]);
            }

            $villageStmt = $serverDB->prepare("SELECT kid FROM vdata WHERE owner=:uid ORDER BY capital DESC LIMIT 1");
            $villageStmt->execute(['uid' => $this->payload['uid']]);
            $village = $villageStmt->fetch(PDO::FETCH_ASSOC);

            if ($village) {
                $serverDB->prepare("
                    UPDATE vdata 
                    SET wood=wood+:wood, clay=clay+:clay, iron=iron+:iron, crop=crop+:crop 
                    WHERE kid=:kid
                ")->execute([
                    'wood' => $rewards['wood'],
                    'clay' => $rewards['clay'],
                    'iron' => $rewards['iron'],
                    'crop' => $rewards['crop'],
                    'kid' => $village['kid']
                ]);
            }

            $heroCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'hero_profile')");
            if ($heroCheck->fetchColumn()) {
                $serverDB->prepare("UPDATE hero_profile SET experience=experience+:xp WHERE uid=:uid")
                          ->execute(['xp' => $rewards['xp'], 'uid' => $this->payload['uid']]);
            }
        }

        $serverDB->prepare("
            UPDATE quest_progress 
            SET completed=true, rewarded=true, completed_at=CURRENT_TIMESTAMP 
            WHERE uid=:uid AND quest_id=:qid
        ")->execute([
            'uid' => $this->payload['uid'],
            'qid' => $this->payload['questId']
        ]);

        $this->response = [
            'success' => true,
            'questId' => (int)$this->payload['questId'],
            'rewards' => [
                'gold' => (int)$rewards['gold'],
                'resources' => [
                    'wood' => (int)$rewards['wood'],
                    'clay' => (int)$rewards['clay'],
                    'iron' => (int)$rewards['iron'],
                    'crop' => (int)$rewards['crop']
                ],
                'experience' => (int)$rewards['xp']
            ]
        ];
    }

    public function getQuestRewards()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['questId'])) {
            throw new MissingParameterException('questId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureQuestTablesExist($serverDB);

        $stmt = $serverDB->prepare("SELECT * FROM quest_rewards WHERE quest_id=:qid");
        $stmt->bindValue('qid', $this->payload['questId'], PDO::PARAM_INT);
        $stmt->execute();

        $rewards = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rewards) {
            $this->response = ['error' => 'Quest not found'];
            return;
        }

        $this->response = [
            'questId' => (int)$this->payload['questId'],
            'title' => $rewards['title'],
            'description' => $rewards['description'],
            'rewards' => [
                'gold' => (int)$rewards['gold'],
                'resources' => [
                    'wood' => (int)$rewards['wood'],
                    'clay' => (int)$rewards['clay'],
                    'iron' => (int)$rewards['iron'],
                    'crop' => (int)$rewards['crop']
                ],
                'experience' => (int)$rewards['xp'],
                'troops' => $rewards['troops'] ? json_decode($rewards['troops'], true) : null,
                'items' => $rewards['items'] ? json_decode($rewards['items'], true) : null
            ]
        ];
    }

    public function skipQuest()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['questId'])) {
            throw new MissingParameterException('questId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureQuestTablesExist($serverDB);

        $rewardStmt = $serverDB->prepare("SELECT quest_type FROM quest_rewards WHERE quest_id=:qid");
        $rewardStmt->execute(['qid' => $this->payload['questId']]);
        $questType = $rewardStmt->fetchColumn();

        if ($questType !== 'tutorial') {
            $this->response = ['error' => 'Only tutorial quests can be skipped'];
            return;
        }

        $cost = 5;

        $userStmt = $serverDB->prepare("SELECT gold FROM users WHERE id=:uid");
        $userStmt->execute(['uid' => $this->payload['uid']]);
        $gold = (int)$userStmt->fetchColumn();

        if ($gold < $cost) {
            $this->response = ['error' => 'Insufficient gold'];
            return;
        }

        $serverDB->prepare("UPDATE users SET gold=gold-:cost WHERE id=:uid")
                  ->execute(['cost' => $cost, 'uid' => $this->payload['uid']]);

        $serverDB->prepare("
            UPDATE quest_progress 
            SET completed=true, rewarded=false, completed_at=CURRENT_TIMESTAMP 
            WHERE uid=:uid AND quest_id=:qid
        ")->execute([
            'uid' => $this->payload['uid'],
            'qid' => $this->payload['questId']
        ]);

        $this->response = [
            'success' => true,
            'questId' => (int)$this->payload['questId'],
            'goldSpent' => $cost
        ];
    }

    public function getQuestProgress()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['questId'])) {
            throw new MissingParameterException('questId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureQuestTablesExist($serverDB);

        $stmt = $serverDB->prepare("
            SELECT qp.*, qr.title, qr.description
            FROM quest_progress qp
            JOIN quest_rewards qr ON qp.quest_id = qr.quest_id
            WHERE qp.uid=:uid AND qp.quest_id=:qid
        ");
        $stmt->execute([
            'uid' => $this->payload['uid'],
            'qid' => $this->payload['questId']
        ]);

        $progress = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$progress) {
            $this->response = ['error' => 'Quest progress not found'];
            return;
        }

        $this->response = [
            'questId' => (int)$progress['quest_id'],
            'title' => $progress['title'],
            'description' => $progress['description'],
            'progress' => (int)$progress['progress'],
            'required' => (int)$progress['required'],
            'completed' => (bool)$progress['completed'],
            'rewarded' => (bool)$progress['rewarded'],
            'startedAt' => $progress['started_at'],
            'completedAt' => $progress['completed_at']
        ];
    }

    private function ensureQuestTablesExist($serverDB)
    {
        $serverDB->exec("CREATE TABLE IF NOT EXISTS quest_progress (
            id SERIAL PRIMARY KEY,
            uid INTEGER NOT NULL,
            quest_id INTEGER NOT NULL,
            progress INTEGER DEFAULT 0,
            required INTEGER DEFAULT 1,
            completed BOOLEAN DEFAULT FALSE,
            rewarded BOOLEAN DEFAULT FALSE,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP,
            UNIQUE(uid, quest_id)
        )");

        $serverDB->exec("CREATE TABLE IF NOT EXISTS quest_rewards (
            quest_id INTEGER PRIMARY KEY,
            quest_type VARCHAR(20) DEFAULT 'tutorial',
            title VARCHAR(255),
            description TEXT,
            gold INTEGER DEFAULT 0,
            wood INTEGER DEFAULT 0,
            clay INTEGER DEFAULT 0,
            iron INTEGER DEFAULT 0,
            crop INTEGER DEFAULT 0,
            xp INTEGER DEFAULT 0,
            troops TEXT,
            items TEXT
        )");
    }
}

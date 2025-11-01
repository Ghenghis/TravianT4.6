<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use Exceptions\NotFoundException;
use Helpers\RedisCache;
use PDO;

class MessagesCtrl extends ApiAbstractCtrl
{
    public function getInbox()
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
        $this->ensureMessageTablesExist($serverDB);

        $folder = $this->payload['folder'] ?? 'inbox';
        $limit = isset($this->payload['limit']) ? min((int)$this->payload['limit'], 100) : 50;
        $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;

        $query = "
            SELECT m.*, 
                   sender.name as sender_name,
                   recipient.name as recipient_name
            FROM messages m
            LEFT JOIN users sender ON m.from_uid = sender.id
            LEFT JOIN users recipient ON m.to_uid = recipient.id
            WHERE ";
        
        if ($folder === 'inbox') {
            $query .= "m.to_uid=:uid AND m.folder='inbox'";
        } elseif ($folder === 'sent') {
            $query .= "m.from_uid=:uid";
        } elseif ($folder === 'archive') {
            $query .= "m.to_uid=:uid AND m.folder='archive'";
        } elseif ($folder === 'trash') {
            $query .= "m.to_uid=:uid AND m.folder='trash'";
        }

        $query .= " ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $serverDB->prepare($query);
        $stmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $messages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $messages[] = [
                'id' => (int)$row['id'],
                'threadId' => (int)$row['thread_id'],
                'from' => [
                    'userId' => (int)$row['from_uid'],
                    'name' => $row['sender_name']
                ],
                'to' => [
                    'userId' => (int)$row['to_uid'],
                    'name' => $row['recipient_name']
                ],
                'subject' => $row['subject'],
                'preview' => substr($row['body'], 0, 100),
                'isRead' => (bool)$row['is_read'],
                'folder' => $row['folder'],
                'createdAt' => $row['created_at']
            ];
        }

        $countQuery = str_replace("SELECT m.*, sender.name as sender_name, recipient.name as recipient_name", "SELECT COUNT(*)", $query);
        $countQuery = str_replace("ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset", "", $countQuery);
        $countStmt = $serverDB->prepare($countQuery);
        $countStmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $this->response = [
            'messages' => $messages,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ];
    }

    public function getMessage()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['messageId'])) {
            throw new MissingParameterException('messageId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureMessageTablesExist($serverDB);

        $stmt = $serverDB->prepare("
            SELECT m.*, 
                   sender.name as sender_name,
                   recipient.name as recipient_name
            FROM messages m
            LEFT JOIN users sender ON m.from_uid = sender.id
            LEFT JOIN users recipient ON m.to_uid = recipient.id
            WHERE m.id=:id
        ");
        $stmt->bindValue('id', $this->payload['messageId'], PDO::PARAM_INT);
        $stmt->execute();

        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$message) {
            $this->response = ['error' => 'Message not found'];
            return;
        }

        if (!$message['is_read']) {
            $serverDB->prepare("UPDATE messages SET is_read=true WHERE id=:id")
                      ->execute(['id' => $this->payload['messageId']]);
        }

        $this->response = [
            'message' => [
                'id' => (int)$message['id'],
                'threadId' => (int)$message['thread_id'],
                'from' => [
                    'userId' => (int)$message['from_uid'],
                    'name' => $message['sender_name']
                ],
                'to' => [
                    'userId' => (int)$message['to_uid'],
                    'name' => $message['recipient_name']
                ],
                'subject' => $message['subject'],
                'body' => $message['body'],
                'isRead' => true,
                'folder' => $message['folder'],
                'createdAt' => $message['created_at']
            ]
        ];
    }

    public function sendMessage()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['fromUid'])) {
            throw new MissingParameterException('fromUid');
        }
        if (!isset($this->payload['toUid'])) {
            throw new MissingParameterException('toUid');
        }
        if (!isset($this->payload['subject'])) {
            throw new MissingParameterException('subject');
        }
        if (!isset($this->payload['body'])) {
            throw new MissingParameterException('body');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureMessageTablesExist($serverDB);

        $threadId = $this->payload['threadId'] ?? null;

        if (!$threadId) {
            $threadStmt = $serverDB->prepare("
                INSERT INTO messages_threads (subject, participants, last_message_at)
                VALUES (:subject, :participants, CURRENT_TIMESTAMP)
            ");
            $participants = json_encode([(int)$this->payload['fromUid'], (int)$this->payload['toUid']]);
            $threadStmt->execute([
                'subject' => $this->payload['subject'],
                'participants' => $participants
            ]);
            $threadId = $serverDB->lastInsertId();
        }

        $stmt = $serverDB->prepare("
            INSERT INTO messages (thread_id, from_uid, to_uid, subject, body, is_read, folder, created_at)
            VALUES (:thread, :from, :to, :subject, :body, false, 'inbox', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            'thread' => $threadId,
            'from' => $this->payload['fromUid'],
            'to' => $this->payload['toUid'],
            'subject' => $this->payload['subject'],
            'body' => $this->payload['body']
        ]);

        $messageId = $serverDB->lastInsertId();

        $serverDB->prepare("UPDATE messages_threads SET last_message_at=CURRENT_TIMESTAMP WHERE id=:id")
                  ->execute(['id' => $threadId]);

        $this->response = [
            'success' => true,
            'messageId' => (int)$messageId,
            'threadId' => (int)$threadId
        ];
    }

    public function deleteMessage()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['messageId'])) {
            throw new MissingParameterException('messageId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureMessageTablesExist($serverDB);

        $permanent = isset($this->payload['permanent']) && $this->payload['permanent'];

        if ($permanent) {
            $stmt = $serverDB->prepare("DELETE FROM messages WHERE id=:id");
            $stmt->execute(['id' => $this->payload['messageId']]);
        } else {
            $stmt = $serverDB->prepare("UPDATE messages SET folder='trash' WHERE id=:id");
            $stmt->execute(['id' => $this->payload['messageId']]);
        }

        $this->response = [
            'success' => true,
            'deleted' => $stmt->rowCount() > 0,
            'permanent' => $permanent
        ];
    }

    public function archiveMessage()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['messageId'])) {
            throw new MissingParameterException('messageId');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureMessageTablesExist($serverDB);

        $stmt = $serverDB->prepare("UPDATE messages SET folder='archive' WHERE id=:id");
        $stmt->execute(['id' => $this->payload['messageId']]);

        $this->response = [
            'success' => true,
            'archived' => $stmt->rowCount() > 0
        ];
    }

    public function getAllianceMessages()
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
        $this->ensureMessageTablesExist($serverDB);

        $limit = isset($this->payload['limit']) ? min((int)$this->payload['limit'], 100) : 50;
        $offset = isset($this->payload['offset']) ? (int)$this->payload['offset'] : 0;

        $stmt = $serverDB->prepare("
            SELECT am.*, u.name as sender_name
            FROM alliance_messages am
            LEFT JOIN users u ON am.sender_uid = u.id
            WHERE am.alliance_id=:aid
            ORDER BY am.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('aid', $this->payload['allianceId'], PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $messages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $messages[] = [
                'id' => (int)$row['id'],
                'sender' => [
                    'userId' => (int)$row['sender_uid'],
                    'name' => $row['sender_name']
                ],
                'message' => $row['message'],
                'isAnnouncement' => (bool)$row['is_announcement'],
                'createdAt' => $row['created_at']
            ];
        }

        $countStmt = $serverDB->prepare("SELECT COUNT(*) FROM alliance_messages WHERE alliance_id=:aid");
        $countStmt->execute(['aid' => $this->payload['allianceId']]);
        $total = (int)$countStmt->fetchColumn();

        $this->response = [
            'messages' => $messages,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ];
    }

    public function sendAllianceMessage()
    {
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['allianceId'])) {
            throw new MissingParameterException('allianceId');
        }
        if (!isset($this->payload['senderUid'])) {
            throw new MissingParameterException('senderUid');
        }
        if (!isset($this->payload['message'])) {
            throw new MissingParameterException('message');
        }

        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            throw new NotFoundException('World not found');
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureMessageTablesExist($serverDB);

        $isAnnouncement = isset($this->payload['isAnnouncement']) && $this->payload['isAnnouncement'];

        $stmt = $serverDB->prepare("
            INSERT INTO alliance_messages (alliance_id, sender_uid, message, is_announcement, created_at)
            VALUES (:aid, :uid, :msg, :announce, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            'aid' => $this->payload['allianceId'],
            'uid' => $this->payload['senderUid'],
            'msg' => $this->payload['message'],
            'announce' => $isAnnouncement
        ]);

        $this->response = [
            'success' => true,
            'messageId' => (int)$serverDB->lastInsertId()
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

        $cacheKey = "messages_unread:{$this->payload['worldId']}:{$this->payload['uid']}";
        $cache = RedisCache::getInstance();
        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            $this->response = $cached;
            return;
        }

        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        $this->ensureMessageTablesExist($serverDB);

        $stmt = $serverDB->prepare("SELECT COUNT(*) FROM messages WHERE to_uid=:uid AND is_read=false AND folder='inbox'");
        $stmt->execute(['uid' => $this->payload['uid']]);
        $inboxCount = (int)$stmt->fetchColumn();

        $userStmt = $serverDB->prepare("SELECT alliance_id FROM users WHERE id=:uid");
        $userStmt->execute(['uid' => $this->payload['uid']]);
        $allianceId = $userStmt->fetchColumn();

        $allianceCount = 0;
        if ($allianceId) {
            $lastSeenStmt = $serverDB->prepare("
                SELECT COUNT(*) FROM alliance_messages 
                WHERE alliance_id=:aid AND created_at > (
                    SELECT COALESCE(MAX(created_at), '1970-01-01') 
                    FROM alliance_messages 
                    WHERE alliance_id=:aid AND sender_uid=:uid
                )
            ");
            $lastSeenStmt->execute([
                'aid' => $allianceId,
                'uid' => $this->payload['uid']
            ]);
            $allianceCount = (int)$lastSeenStmt->fetchColumn();
        }

        $this->response = [
            'unreadCount' => [
                'inbox' => $inboxCount,
                'alliance' => $allianceCount,
                'total' => $inboxCount + $allianceCount
            ]
        ];

        $cache->set($cacheKey, $this->response, 30);
    }

    private function ensureMessageTablesExist($serverDB)
    {
        $serverDB->exec("CREATE TABLE IF NOT EXISTS messages_threads (
            id SERIAL PRIMARY KEY,
            subject VARCHAR(255),
            participants TEXT,
            last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $serverDB->exec("CREATE TABLE IF NOT EXISTS messages (
            id SERIAL PRIMARY KEY,
            thread_id INTEGER,
            from_uid INTEGER NOT NULL,
            to_uid INTEGER NOT NULL,
            subject VARCHAR(255),
            body TEXT,
            is_read BOOLEAN DEFAULT FALSE,
            folder VARCHAR(20) DEFAULT 'inbox',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $serverDB->exec("CREATE TABLE IF NOT EXISTS alliance_messages (
            id SERIAL PRIMARY KEY,
            alliance_id INTEGER NOT NULL,
            sender_uid INTEGER NOT NULL,
            message TEXT,
            is_announcement BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
}

<?php
namespace Core;
use Database\DB;
use function file_get_contents;
use function json_encode;
use PDO;
class Server
{
    public static function getServerById($id)
    {
        $db = DB::getInstance();
        $stmt = $db->prepare("SELECT * FROM gameServers WHERE id=:id");
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->rowCount()) {
            return FALSE;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return self::mapColumnNames($row);
    }
    
    // Map PostgreSQL lowercase column names to camelCase for backward compatibility
    private static function mapColumnNames($row)
    {
        if (!$row) return $row;
        return [
            'id' => $row['id'],
            'worldId' => $row['worldid'] ?? $row['worldId'],
            'speed' => $row['speed'],
            'name' => $row['name'],
            'version' => $row['version'] ?? null,
            'gameWorldUrl' => $row['gameworldurl'] ?? $row['gameWorldUrl'],
            'startTime' => $row['starttime'] ?? $row['startTime'],
            'roundLength' => $row['roundlength'] ?? $row['roundLength'],
            'finished' => $row['finished'],
            'registerClosed' => $row['registerclosed'] ?? $row['registerClosed'],
            'activation' => $row['activation'],
            'preregistration_key_only' => $row['preregistration_key_only'],
            'hidden' => $row['hidden'],
            'promoted' => $row['promoted'] ?? null,
            'configFileLocation' => $row['configfilelocation'] ?? $row['configFileLocation'],
        ];
    }

    public static function getServerByWId($wid)
    {
        $db = DB::getInstance();
        $stmt = $db->prepare("SELECT * FROM gameServers WHERE worldId=:wid");
        $stmt->bindValue('wid', $wid, PDO::PARAM_STR);
        $stmt->execute();
        if (!$stmt->rowCount()) {
            return FALSE;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return self::mapColumnNames($row);
    }

    public static function getGameWorldsList($includeDev = false)
    {
        $result = [];
        $db = DB::getInstance();
        $stmt = $db->query("SELECT * FROM gameServers");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$includeDev && $row['hidden'] == 1) continue;
            $server = [
                "id" => $row['id'],
                "uuid" => sha1($row['id']),
                "title" => $row['name'],
                "name" => $row['worldId'],
                "url" => $row['gameWorldUrl'],
                "status" => $row['registerClosed'] == 1 || $row['finished'] == 1 ? 0 : 1,
                "registrationKeyRequired" => $row['preregistration_key_only'] == 1,
                "start" => $row['startTime'],
            ];
            if (substr($row['worldId'], -2) == 'tt') {
                $server['fireAndSand'] = 'yes';
            }
            $result[] = $server;
        }

        return $result;
    }

    public static function getGameWorldsListForRegistration($includeDev = false)
    {
        $result = [];
        $db = DB::getInstance();
        $stmt = $db->query("SELECT * FROM gameServers WHERE finished=0 AND registerClosed=0");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$includeDev && $row['hidden'] == 1) continue;
            $server = [
                "id" => $row['id'],
                "uuid" => sha1($row['id']),
                "title" => $row['name'],
                "name" => $row['worldId'],
                "url" => $row['gameWorldUrl'],
                "status" => 1,
                "registrationKeyRequired" => $row['preregistration_key_only'] == 1,
                "start" => $row['startTime']
            ];
            if (substr($row['worldId'], -2) == 'tt') {
                $server['fireAndSand'] = 'yes';
            }
            $result[] = $server;
        }
        return $result;
    }
}
<?php

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\EmailService;
use Core\LoginOperator;
use Core\Server;
use Database\DB;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use PDO;
use const FILTER_SANITIZE_EMAIL;
use const FILTER_SANITIZE_STRING;
use function filter_var;

class AuthCtrl extends ApiAbstractCtrl
{
    public function updatePassword()
    {
        if (!isset($this->payload['recoveryCode'])) {
            throw new MissingParameterException('recoveryCode');
        }
        if (!isset($this->payload['worldId'])) {
            throw new MissingParameterException('worldId');
        }
        if (!isset($this->payload['uid'])) {
            throw new MissingParameterException('uid');
        }
        if (!isset($this->payload['password'])) {
            throw new MissingParameterException('password');
        }
        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            $this->response['fields']['password'] = 'unknownGameWorld';
            return;
        }
        $this->response['success'] = false;
        $this->payload['recoveryCode'] = filter_var($this->payload['recoveryCode'], FILTER_SANITIZE_STRING);
        $recovery = $this->findRecoveryByRecoveryCode(
            (int)$this->payload['uid'], (int)$server['id'], $this->payload['recoveryCode']);
        if ($recovery) {
            $password = $this->payload['password'];
            if (strlen($password) < 4) {
                return;
            }
            if (empty($password)) {
                return;
            }
            $db = DB::getInstance();
            $db->query("DELETE FROM passwordRecovery WHERE id={$recovery['id']}");
            
            try {
                $serverDB = ServerDB::getInstance($server['configFileLocation']);
            } catch (\Exception $e) {
                error_log("MySQL Connection Error for world {$server['worldId']}: " . $e->getMessage());
                $this->response['fields']['password'] = 'databaseConnectionError';
                $this->response['error'] = [
                    'message' => 'Database not configured for this world. Please run MySQL setup script.',
                    'details' => 'For Windows: Run .\\scripts\\setup-windows.ps1 to initialize MySQL databases.',
                    'worldId' => $server['worldId']
                ];
                return;
            }
            
            $stmt = $serverDB->prepare("UPDATE users SET password=:password WHERE id=:id");
            $stmt->bindValue('password', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), PDO::PARAM_STR);
            $stmt->bindValue('id', $recovery['uid'], PDO::PARAM_INT);
            $stmt->execute();
            $this->response['success'] = true;
        } else {
            $this->response['fields']['password'] = 'passwordWasNotUpdated';
        }
    }

    private function findRecoveryByRecoveryCode($uid, $wid, $recoveryCode)
    {
        $db = DB::getInstance();
        $stmt = $db->prepare('SELECT * FROM passwordRecovery WHERE uid=:uid AND wid=:wid AND recoveryCode=:recoveryCode');
        $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue('wid', $wid, PDO::PARAM_INT);
        $stmt->bindValue('recoveryCode', $recoveryCode, PDO::PARAM_STR);
        $stmt->execute();
        if (!$stmt->rowCount()) return false;
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function forgotGameWorld()
    {
        if (!isset($this->payload['email'])) {
            throw new MissingParameterException('email');
        }
        $gameWorlds = [];
        $connectionErrors = 0;
        $totalServers = 0;
        $captcha = isset($this->payload['captcha']) ? $this->payload['captcha'] : null;
        $this->response['success'] = false;
        $this->payload['email'] = filter_var($this->payload['email'], FILTER_SANITIZE_EMAIL);
        $db = DB::getInstance();
        $serverFindStatement = $db->query("SELECT worldId, gameWorldUrl, configFileLocation FROM gameServers WHERE finished=0 ORDER BY startTime DESC");
        while ($server = $serverFindStatement->fetch(PDO::FETCH_ASSOC)) {
            $totalServers++;
            try {
                $serverDB = ServerDB::getInstance($server['configFileLocation']);
                $stmt = $serverDB->prepare("SELECT name FROM users WHERE email=:email");
                $stmt->bindValue('email', $this->payload['email'], PDO::PARAM_STR);
                $stmt->execute();
                if ($stmt->rowCount()) {
                    $gameWorlds[] = [
                        'worldId' => $server['worldId'],
                        'username' => $stmt->fetchColumn(),
                        'gameWorldUrl' => $server['gameWorldUrl']
                    ];
                }
            } catch (\Exception $e) {
                $connectionErrors++;
                error_log("MySQL Connection Error for world {$server['worldId']} in forgotGameWorld: " . $e->getMessage());
                continue;
            }
        }
        
        if (!sizeof($gameWorlds)) {
            if ($connectionErrors > 0 && $connectionErrors === $totalServers) {
                $this->response['fields']['email'] = 'databaseConnectionError';
                $this->response['error'] = [
                    'message' => 'Database not configured for any game worlds. Please run MySQL setup script.',
                    'details' => 'For Windows: Run .\\scripts\\setup-windows.ps1 to initialize MySQL databases.',
                    'failedWorlds' => $connectionErrors
                ];
            } else {
                $this->response['fields']['email'] = 'noAccountsAssociatedWithEmailAddress';
            }
            return;
        }
        $this->response['success'] = true;
        EmailService::sendForgottenAccounts($this->payload['email'], $gameWorlds);
    }

    public function forgotPassword()
    {
        if (!isset($this->payload['email'])) {
            throw new MissingParameterException('email');
        }
        if (!isset($this->payload['gameWorldId'])) {
            throw new MissingParameterException('gameWorldId');
        }
        $captcha = isset($this->payload['captcha']) ? $this->payload['captcha'] : null;
        $this->response['success'] = false;
        $this->payload['email'] = filter_var($this->payload['email'], FILTER_SANITIZE_EMAIL);
        $server = Server::getServerById((int)$this->payload['gameWorldId']);
        if (!$server) {
            $this->response['fields']['email'] = 'unknownGameWorld';
            return;
        }
        
        try {
            $serverDB = ServerDB::getInstance($server['configFileLocation']);
        } catch (\Exception $e) {
            error_log("MySQL Connection Error for world {$server['worldId']}: " . $e->getMessage());
            $this->response['fields']['email'] = 'databaseConnectionError';
            $this->response['error'] = [
                'message' => 'Database not configured for this world. Please run MySQL setup script.',
                'details' => 'For Windows: Run .\\scripts\\setup-windows.ps1 to initialize MySQL databases.',
                'worldId' => $server['worldId']
            ];
            return;
        }
        
        $loginHelper = new LoginOperator($serverDB);
        $find = $loginHelper->findLogin($server['id'], $this->payload['email']);
        if (!$find['type'] || $find['type'] != 1) {
            $this->response['fields']['email'] = 'emailUnknown';
            return;
        }
        $uid = $find['row']['id'];
        $recoveryCode = $this->addNewPassword($uid, $server['id']);
        EmailService::sendPasswordForgotten($this->payload['email'], $server['id'], $server['worldId'], $uid, $recoveryCode);
        $this->response['success'] = true;
    }

    private function addNewPassword($uid, $wid)
    {
        $db = DB::getInstance();
        $recoveryCode = get_random_string(mt_rand(7, 13));
        {
            $stmt = $db->prepare("DELETE FROM passwordRecovery WHERE uid=:uid AND wid=:wid");
            $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
            $stmt->bindValue('wid', $wid, PDO::PARAM_INT);
            $stmt->execute();
        }
        {
            $stmt = $db->prepare('INSERT INTO passwordRecovery (uid, wid, recoveryCode) VALUES (:uid, :wid, :recoveryCode)');
            $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
            $stmt->bindValue('wid', $wid, PDO::PARAM_INT);
            $stmt->bindValue('recoveryCode', $recoveryCode, PDO::PARAM_STR);
            $stmt->execute();
        }
        return $recoveryCode;
    }

    public function login()
    {
        $needs = ['gameWorldId', 'password', 'usernameOrEmail'];
        foreach ($needs as $k) {
            if (!isset($this->payload[$k])) {
                throw new MissingParameterException($k);
            }
        }
        $gameWorldId = (int)$this->payload['gameWorldId'];
        $usernameOrEmail = trim($this->payload['usernameOrEmail']);
        $captcha = isset($this->payload['captcha']) ? $this->payload['captcha'] : null;
        /*if (empty($captcha)) {
            $this->response['fields']['captcha'] = 'reCaptchaRequired';
            return;
        }*/
        $lowResMode = isset($this->payload['lowResMode']) && $this->payload['lowResMode'] ? true : false;
        $password = $this->payload['password'];
        $server = Server::getServerById($gameWorldId);
        if ($server == false) {
            $this->response['fields']['usernameOrEmail'] = 'unknownGameWorld';
            return;
        }
        if ($server['startTime'] > time()) {
            $this->response['fields']['usernameOrEmail'] = 'gameWorldNotStartedYet';
            return;
        }
        $this->response = [];
        
        try {
            $serverDB = ServerDB::getInstance($server['configFileLocation']);
        } catch (\Exception $e) {
            error_log("MySQL Connection Error for world {$server['worldId']}: " . $e->getMessage());
            $this->response['fields']['usernameOrEmail'] = 'databaseConnectionError';
            $this->response['error'] = [
                'message' => 'Database not configured for this world. Please run MySQL setup script.',
                'details' => 'For Windows: Run .\\scripts\\setup-windows.ps1 to initialize MySQL databases.',
                'worldId' => $server['worldId'],
                'configFile' => $server['configFileLocation']
            ];
            return;
        }
        
        $loginHelper = new LoginOperator($serverDB);
        $find = $loginHelper->findLogin($server['id'], $usernameOrEmail);
        if (!$find['type'] || !isset($find['row']['id'])) {
            $this->response['fields']['usernameOrEmail'] = 'userDoesNotExists';
            return;
        }
        $result = $loginHelper->checkLogin($password, $find);
        if ($result <> 3) {
            switch ($find['type']) {
                case 1:
                    if ($result <> 0 && $find['row']['last_owner_login_time'] <= time() - 21 * 86400) {
                        $this->response['fields']['password'] = 'accountIsInactive';
                        return;
                    }
                    $handshake = $loginHelper->insertHandshake($find['row']['id'], $result <> 0);
                    $this->response['redirect'] = $server['gameWorldUrl'] . 'login.php?detectLang&lowRes=' . ($lowResMode ? 1 : 0) . '&handshake=' . $handshake;
                    return;
                case 2:
                    $this->response['redirect'] = $server['gameWorldUrl'] . 'activate.php?detectLang&token=' . $find['row']['token'];
                    return;
            }
        } else {
            $this->response['fields']['password'] = 'passwordWrong';
        }
    }
}
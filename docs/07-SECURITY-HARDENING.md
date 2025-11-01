# Security Hardening

## Overview

This guide covers enterprise-grade security practices for TravianT4.6 production deployment.

## Step 1: Server Security

### SSH Hardening

Edit `/etc/ssh/sshd_config`:

```bash
sudo nano /etc/ssh/sshd_config
```

Configure:

```
# Disable root login
PermitRootLogin no

# Disable password authentication (use keys only)
PasswordAuthentication no
PubkeyAuthentication yes

# Disable empty passwords
PermitEmptyPasswords no

# Change default port (optional)
Port 2222

# Limit login attempts
MaxAuthTries 3
MaxSessions 2

# Disable X11 Forwarding
X11Forwarding no

# Allow specific users only
AllowUsers travian

# Set idle timeout
ClientAliveInterval 300
ClientAliveCountMax 2
```

Restart SSH:

```bash
sudo systemctl restart sshd
```

### Install Fail2Ban

```bash
# Install
sudo apt install fail2ban -y

# Create jail configuration
sudo nano /etc/fail2ban/jail.local
```

Add:

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
destemail = admin@travian.com
sendername = Fail2Ban

[sshd]
enabled = true
port = 2222
logpath = %(sshd_log)s
backend = %(sshd_backend)s

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
port = http,https
logpath = /var/www/travian/docker/nginx/logs/error.log

[nginx-noscript]
enabled = true
port = http,https
filter = nginx-noscript
logpath = /var/www/travian/docker/nginx/logs/access.log

[nginx-badbots]
enabled = true
port = http,https
filter = nginx-badbots
logpath = /var/www/travian/docker/nginx/logs/access.log

[nginx-noproxy]
enabled = true
port = http,https
filter = nginx-noproxy
logpath = /var/www/travian/docker/nginx/logs/access.log
```

Start Fail2Ban:

```bash
sudo systemctl start fail2ban
sudo systemctl enable fail2ban
sudo fail2ban-client status
```

### System Updates

```bash
# Enable automatic security updates
sudo apt install unattended-upgrades -y
sudo dpkg-reconfigure --priority=low unattended-upgrades
```

Configure `/etc/apt/apt.conf.d/50unattended-upgrades`:

```
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};
Unattended-Upgrade::AutoFixInterruptedDpkg "true";
Unattended-Upgrade::Automatic-Reboot "false";
```

## Step 2: Application Security

### Environment Variables Protection

```bash
# Secure .env file
chmod 600 /var/www/travian/.env
chown travian:travian /var/www/travian/.env

# Never commit .env to git
echo ".env" >> .gitignore
```

### Secure globalConfig.php

Update `sections/globalConfig.php`:

```php
<?php
// Prevent direct access
if (!defined('INCLUDE_CHECK')) {
    die('Direct access not permitted');
}

// Production error reporting
if (getenv('APP_ENV') === 'production') {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', '/var/www/html/storage/logs/php-error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Security constants
define('SECURE_HASH_SALT', getenv('SECURE_HASH_SALT'));
if (empty(SECURE_HASH_SALT)) {
    throw new Exception('SECURE_HASH_SALT not configured');
}

// Session security
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', getenv('COOKIE_SECURE') === 'true' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');

// ... rest of config
```

### SQL Injection Prevention

Always use prepared statements in `sections/api/include/Database/DB.php`:

```php
// BAD - Vulnerable to SQL injection
$query = "SELECT * FROM users WHERE id = " . $_GET['id'];

// GOOD - Use prepared statements
$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$stmt->bindValue('id', $id, PDO::PARAM_INT);
$stmt->execute();
```

### XSS Protection

Create input sanitization helper `sections/api/include/Core/Security.php`:

```php
<?php
namespace Core;

class Security
{
    public static function sanitizeInput($input, $type = 'string')
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        switch ($type) {
            case 'int':
                return (int)$input;
            case 'float':
                return (float)$input;
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            case 'string':
            default:
                return strip_tags(trim($input));
        }
    }
    
    public static function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }
    
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }
    
    public static function preventCSRF()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRF($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
}
```

### Rate Limiting

Create `sections/api/include/Middleware/RateLimiter.php`:

```php
<?php
namespace Middleware;

use Redis;

class RateLimiter
{
    private $redis;
    private $maxAttempts = 60;
    private $decayMinutes = 1;
    
    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect(getenv('REDIS_HOST'), 6379);
    }
    
    public function tooManyAttempts($key, $maxAttempts = null, $decayMinutes = null)
    {
        $max = $maxAttempts ?: $this->maxAttempts;
        $decay = $decayMinutes ?: $this->decayMinutes;
        
        $attempts = $this->redis->get($key) ?: 0;
        
        if ($attempts >= $max) {
            return true;
        }
        
        return false;
    }
    
    public function hit($key, $decayMinutes = null)
    {
        $decay = $decayMinutes ?: $this->decayMinutes;
        
        $this->redis->incr($key);
        $this->redis->expire($key, $decay * 60);
    }
    
    public function clear($key)
    {
        $this->redis->del($key);
    }
    
    public function attempts($key)
    {
        return (int)$this->redis->get($key) ?: 0;
    }
}
```

## Step 3: Database Security

### MySQL User Permissions

```sql
-- Create read-only user for reporting
CREATE USER 'travian_readonly'@'%' IDENTIFIED BY 'readonly_password';
GRANT SELECT ON travian_global.* TO 'travian_readonly'@'%';
GRANT SELECT ON travian_testworld.* TO 'travian_readonly'@'%';

-- Create backup user
CREATE USER 'travian_backup'@'localhost' IDENTIFIED BY 'backup_password';
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON *.* TO 'travian_backup'@'localhost';

-- Remove unnecessary privileges
REVOKE FILE ON *.* FROM 'travian_user'@'%';
REVOKE SUPER ON *.* FROM 'travian_user'@'%';

FLUSH PRIVILEGES;
```

### Enable MySQL SSL

Create certificate in `docker/mysql/certs/`:

```bash
# Generate certificates
mkdir -p docker/mysql/certs
cd docker/mysql/certs

openssl req -newkey rsa:2048 -days 3600 -nodes -keyout ca-key.pem -out ca-req.pem
openssl x509 -req -in ca-req.pem -days 3600 -signkey ca-key.pem -out ca-cert.pem
openssl req -newkey rsa:2048 -days 3600 -nodes -keyout server-key.pem -out server-req.pem
openssl x509 -req -in server-req.pem -days 3600 -CA ca-cert.pem -CAkey ca-key.pem -set_serial 01 -out server-cert.pem

# Set permissions
chmod 400 *.pem
```

Update `docker-compose.yml`:

```yaml
mysql:
  # ...
  volumes:
    - ./docker/mysql/certs:/etc/mysql/certs:ro
  command: >
    --ssl-ca=/etc/mysql/certs/ca-cert.pem
    --ssl-cert=/etc/mysql/certs/server-cert.pem
    --ssl-key=/etc/mysql/certs/server-key.pem
    --require_secure_transport=ON
```

### Encrypt Sensitive Data

Create encryption helper `sections/api/include/Core/Encryption.php`:

```php
<?php
namespace Core;

class Encryption
{
    private static $cipher = 'aes-256-gcm';
    
    public static function encrypt($data)
    {
        $key = self::getKey();
        $ivlen = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $tag = '';
        
        $ciphertext = openssl_encrypt(
            $data,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        return base64_encode($iv . $tag . $ciphertext);
    }
    
    public static function decrypt($encrypted)
    {
        $key = self::getKey();
        $data = base64_decode($encrypted);
        $ivlen = openssl_cipher_iv_length(self::$cipher);
        
        $iv = substr($data, 0, $ivlen);
        $tag = substr($data, $ivlen, 16);
        $ciphertext = substr($data, $ivlen + 16);
        
        return openssl_decrypt(
            $ciphertext,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }
    
    private static function getKey()
    {
        $key = getenv('ENCRYPTION_KEY');
        if (empty($key)) {
            throw new \Exception('ENCRYPTION_KEY not configured');
        }
        return hash('sha256', $key, true);
    }
}
```

## Step 4: Docker Security

### Run Containers as Non-Root

Update `docker/php/Dockerfile`:

```dockerfile
# ... existing config ...

# Create non-root user
RUN groupadd -g 1000 www && \
    useradd -u 1000 -g www -s /bin/bash -m www

# Set ownership
RUN chown -R www:www /var/www/html

# Switch to non-root user
USER www

# ... rest of config ...
```

### Docker Security Scanning

```bash
# Scan images for vulnerabilities
docker scan travian_php:latest
docker scan travian_nginx:latest

# Use Trivy for comprehensive scanning
docker run aquasec/trivy image travian_php:latest
```

### Limit Container Resources

Update `docker-compose.yml`:

```yaml
services:
  php:
    # ...
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G
        reservations:
          cpus: '1.0'
          memory: 512M
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    cap_add:
      - NET_BIND_SERVICE
```

### Docker Secrets

For sensitive data, use Docker secrets:

```yaml
secrets:
  db_password:
    file: ./secrets/db_password.txt
  smtp_password:
    file: ./secrets/smtp_password.txt

services:
  php:
    # ...
    secrets:
      - db_password
      - smtp_password
```

## Step 5: Web Application Firewall

### ModSecurity with Nginx

Add to Nginx configuration:

```nginx
# Enable ModSecurity
modsecurity on;
modsecurity_rules_file /etc/nginx/modsec/main.conf;

# OWASP Core Rule Set
include /etc/nginx/modsec/crs-setup.conf;
include /etc/nginx/modsec/rules/*.conf;
```

### Configure CSP (Content Security Policy)

Add to Nginx:

```nginx
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-src https://www.google.com/recaptcha/;";
```

## Step 6: API Security

### JWT Authentication

Create JWT helper `sections/api/include/Core/JWT.php`:

```php
<?php
namespace Core;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    private static $secret;
    private static $algorithm = 'HS256';
    
    public static function generate($userId, $worldId)
    {
        self::$secret = getenv('JWT_SECRET');
        
        $payload = [
            'iss' => getenv('APP_URL'),
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60), // 24 hours
            'uid' => $userId,
            'wid' => $worldId
        ];
        
        return FirebaseJWT::encode($payload, self::$secret, self::$algorithm);
    }
    
    public static function verify($token)
    {
        try {
            self::$secret = getenv('JWT_SECRET');
            $decoded = FirebaseJWT::decode($token, new Key(self::$secret, self::$algorithm));
            return (array)$decoded;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### API Request Signing

Create request validator:

```php
<?php
namespace Middleware;

class RequestValidator
{
    public static function validateSignature($request, $secret)
    {
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        
        // Check timestamp (prevent replay attacks)
        if (abs(time() - $timestamp) > 300) { // 5 minutes
            return false;
        }
        
        // Verify signature
        $data = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp . $data, $secret);
        
        return hash_equals($expected, $signature);
    }
}
```

## Step 7: Monitoring and Alerting

### Log Monitoring

Create log monitor script `scripts/security-monitor.sh`:

```bash
#!/bin/bash

# Monitor for suspicious activity
LOG_FILE="/var/www/travian/storage/logs/security.log"
ALERT_EMAIL="admin@travian.com"

# Failed login attempts
FAILED_LOGINS=$(grep "Failed login" $LOG_FILE | tail -100 | wc -l)
if [ $FAILED_LOGINS -gt 50 ]; then
    echo "High number of failed logins: $FAILED_LOGINS" | mail -s "Security Alert" $ALERT_EMAIL
fi

# SQL injection attempts
SQL_INJECTION=$(grep -i "union select\|or 1=1\|; drop" /var/log/nginx/access.log | tail -100 | wc -l)
if [ $SQL_INJECTION -gt 0 ]; then
    echo "Possible SQL injection attempts detected" | mail -s "Security Alert" $ALERT_EMAIL
fi

# Suspicious user agents
grep -i "nikto\|sqlmap\|nmap" /var/log/nginx/access.log >> $LOG_FILE
```

Schedule with cron:

```cron
*/15 * * * * /var/www/travian/scripts/security-monitor.sh
```

## Step 8: Security Checklist

### Production Security Checklist

- [ ] SSH key authentication enabled, password auth disabled
- [ ] Firewall configured and enabled
- [ ] Fail2Ban installed and configured
- [ ] Automatic security updates enabled
- [ ] SSL/TLS certificates installed and auto-renewal configured
- [ ] Environment variables secured (not in git)
- [ ] Database users have minimal required privileges
- [ ] Database connections use SSL
- [ ] Sensitive data encrypted at rest
- [ ] All inputs sanitized and validated
- [ ] Prepared statements used for all database queries
- [ ] CSRF protection implemented
- [ ] Rate limiting enabled
- [ ] Security headers configured
- [ ] Docker containers run as non-root
- [ ] Container resources limited
- [ ] Regular security scans scheduled
- [ ] Monitoring and alerting configured
- [ ] Backup encryption enabled
- [ ] Incident response plan documented

## Next Steps

Continue to [08-MONITORING-MAINTENANCE.md](08-MONITORING-MAINTENANCE.md) for monitoring, logging, and maintenance procedures.

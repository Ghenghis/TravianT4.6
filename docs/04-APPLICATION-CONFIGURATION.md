# Application Configuration

## Overview

This guide covers complete application configuration including:
- Environment variables
- Game world setup
- Email services
- reCAPTCHA
- Background workers
- Caching configuration

## Step 1: Environment Variables

### Configure .env File

Copy and customize your environment file:

```bash
cp .env.example .env
```

Edit `.env` with your actual values:

```bash
# Application Environment
APP_ENV=production              # production, staging, development
APP_DEBUG=false                 # true for development, false for production
APP_URL=https://travian.yourdomain.com

# MySQL Database (Global)
MYSQL_ROOT_PASSWORD=YourSecureRootPassword123!
MYSQL_DATABASE=travian_global
MYSQL_USER=travian_user
MYSQL_PASSWORD=YourSecureUserPassword456!
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=travian_global
DB_USERNAME=travian_user
DB_PASSWORD=YourSecureUserPassword456!

# Redis Cache
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=                 # Leave empty if no password

# Email Configuration (SMTP)
SMTP_HOST=smtp.gmail.com        # or smtp.sendgrid.net, smtp.mailgun.org
SMTP_PORT=587                   # 587 for TLS, 465 for SSL
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_ENCRYPTION=tls             # tls or ssl
SMTP_FROM_ADDRESS=noreply@travian.com
SMTP_FROM_NAME="Travian Legends"

# SendinBlue (Optional - for transactional emails)
SENDINBLUE_API_KEY=your-sendinblue-api-key-here

# Google reCAPTCHA v2
RECAPTCHA_SITE_KEY=your-site-key-here
RECAPTCHA_SECRET_KEY=your-secret-key-here

# Game Configuration
DEFAULT_GAME_SPEED=100          # Game speed multiplier
DEFAULT_ROUND_LENGTH=365        # Round length in days
PROTECTION_HOURS=72             # Beginner protection hours

# Security
SESSION_LIFETIME=86400          # 24 hours in seconds
SECURE_HASH_SALT=RandomSaltHere123456789
COOKIE_SECURE=true              # true for HTTPS, false for HTTP
COOKIE_DOMAIN=.travian.com      # Your domain

# Task Worker
WORKER_SLEEP_TIME=5             # Seconds between worker cycles
MAX_WORKER_MEMORY=512M          # Maximum memory for worker

# Logging
LOG_LEVEL=error                 # debug, info, warning, error
LOG_CHANNEL=daily               # daily, single, syslog
```

### Secure Your .env File

```bash
# Set proper permissions (Linux/WSL)
chmod 600 .env

# Add to .gitignore
echo ".env" >> .gitignore
```

## Step 2: Configure Global Settings

### Update sections/globalConfig.php

```php
<?php
/**
 * Global Configuration for TravianT4.6
 */

// Error Reporting (Production)
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'mysql');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'travian_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'travian_global');

// Redis Configuration
define('REDIS_HOST', getenv('REDIS_HOST') ?: 'redis');
define('REDIS_PORT', getenv('REDIS_PORT') ?: 6379);

// Application Settings
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
define('DOMAIN', parse_url(APP_URL, PHP_URL_HOST));
define('DEBUG_MODE', getenv('APP_DEBUG') === 'true');

// Security
define('SECURE_HASH_SALT', getenv('SECURE_HASH_SALT') ?: 'change-this-salt');
define('SESSION_LIFETIME', (int)getenv('SESSION_LIFETIME') ?: 86400);
define('COOKIE_SECURE', getenv('COOKIE_SECURE') === 'true');
define('COOKIE_DOMAIN', getenv('COOKIE_DOMAIN') ?: '');

// Email Settings
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
define('SMTP_FROM_ADDRESS', getenv('SMTP_FROM_ADDRESS') ?: 'noreply@travian.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Travian');

// reCAPTCHA
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '');

// Paths
define('ROOT_PATH', __DIR__);
define('INCLUDE_PATH', ROOT_PATH . '/sections/api/include');
define('TEMPLATE_PATH', INCLUDE_PATH . '/Templates');
define('FILTERING_PATH', ROOT_PATH . '/filtering');

// Composer Autoload
require_once ROOT_PATH . '/sections/api/include/vendor/autoload.php';
```

## Step 3: Set Up Game Worlds

### Create Game World Directory Structure

```bash
# For testworld
mkdir -p sections/servers/testworld/include
mkdir -p sections/servers/testworld/public
mkdir -p sections/servers/testworld/logs

# For demo
mkdir -p sections/servers/demo/include
mkdir -p sections/servers/demo/public
mkdir -p sections/servers/demo/logs

# Copy game world template files
cp -r main_script/* sections/servers/testworld/
cp -r main_script/* sections/servers/demo/
```

### Configure testworld Connection

Create/update `sections/servers/testworld/include/connection.php`:

```php
<?php
global $connection;
$connection = [
    // Game Settings
    'speed' => '100',              // 100x speed
    'round_length' => '365',       // 365 days
    'worldId' => 'testworld',
    'title' => 'Test Server 100x',
    'serverName' => 'Test Server',
    'gameWorldUrl' => 'http://testworld.travian.local/',
    
    // Security
    'secure_hash_code' => 'ea380e814f4913df4e9a73b1de39b06d', // Change this!
    
    // Auto-reinstall (for auto-restart worlds)
    'auto_reinstall' => '0',
    'auto_reinstall_start_after' => '86400',
    'engine_filename' => 'testworld.service',
    
    // Database Configuration
    'database' => [
        'hostname' => getenv('DB_HOST') ?: 'mysql',
        'username' => getenv('DB_USERNAME') ?: 'travian_user',
        'password' => getenv('DB_PASSWORD') ?: '',
        'database' => 'travian_testworld',
        'charset' => 'utf8mb4',
    ],
];
```

### Configure demo World

Create/update `sections/servers/demo/include/connection.php`:

```php
<?php
global $connection;
$connection = [
    'speed' => '5',
    'round_length' => '180',
    'worldId' => 'demo',
    'title' => 'Demo Server 5x',
    'serverName' => 'Demo Server',
    'gameWorldUrl' => 'http://demo.travian.local/',
    'secure_hash_code' => 'f4a7c3d82e9f1b56a08d37e2c41b9f08', // Change this!
    'auto_reinstall' => '0',
    'auto_reinstall_start_after' => '86400',
    'engine_filename' => 'demo.service',
    'database' => [
        'hostname' => getenv('DB_HOST') ?: 'mysql',
        'username' => getenv('DB_USERNAME') ?: 'travian_user',
        'password' => getenv('DB_PASSWORD') ?: '',
        'database' => 'travian_demo',
        'charset' => 'utf8mb4',
    ],
];
```

## Step 4: Email Configuration

### Option 1: Gmail SMTP

1. Enable 2-Factor Authentication on your Gmail account
2. Generate App Password:
   - Go to https://myaccount.google.com/apppasswords
   - Select "Mail" and "Other (Custom name)"
   - Copy the generated password
3. Update .env:

```bash
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-generated-app-password
SMTP_ENCRYPTION=tls
```

### Option 2: SendGrid

1. Sign up at https://sendgrid.com (free tier available)
2. Create API key
3. Update .env:

```bash
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USERNAME=apikey
SMTP_PASSWORD=your-sendgrid-api-key
SMTP_ENCRYPTION=tls
```

### Option 3: Mailgun

1. Sign up at https://www.mailgun.com
2. Get SMTP credentials
3. Update .env:

```bash
SMTP_HOST=smtp.mailgun.org
SMTP_PORT=587
SMTP_USERNAME=postmaster@your-domain.mailgun.org
SMTP_PASSWORD=your-mailgun-password
SMTP_ENCRYPTION=tls
```

### Test Email Configuration

Create `scripts/test-email.php`:

```php
<?php
require_once __DIR__ . '/../sections/globalConfig.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;

    // Recipients
    $mail->setFrom(SMTP_FROM_ADDRESS, SMTP_FROM_NAME);
    $mail->addAddress('test@example.com', 'Test User');

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Travian Email Test';
    $mail->Body    = '<h1>Test Email</h1><p>If you receive this, email is working!</p>';

    $mail->send();
    echo "Test email sent successfully!\n";
} catch (Exception $e) {
    echo "Email could not be sent. Error: {$mail->ErrorInfo}\n";
}
```

Run test:

```bash
docker-compose exec php php /var/www/html/scripts/test-email.php
```

## Step 5: reCAPTCHA Setup

### Get reCAPTCHA Keys

1. Go to https://www.google.com/recaptcha/admin
2. Register your site (reCAPTCHA v2)
3. Add your domains:
   - travian.yourdomain.com
   - testworld.travian.yourdomain.com
4. Copy Site Key and Secret Key

### Update Configuration

In `.env`:

```bash
RECAPTCHA_SITE_KEY=6Lc...your-site-key...xyz
RECAPTCHA_SECRET_KEY=6Lc...your-secret-key...xyz
```

### Enable reCAPTCHA in Code

Update `sections/api/include/Api/Ctrl/RegisterCtrl.php`:

```php
// Uncomment reCAPTCHA validation
if (empty($captcha)) {
    $this->response['fields']['captcha'] = 'reCaptchaRequired';
    return;
}
```

## Step 6: Redis Configuration

### Verify Redis Connection

```bash
# Test Redis
docker-compose exec redis redis-cli ping
# Should return: PONG

# Check Redis info
docker-compose exec redis redis-cli info server
```

### Configure Session Storage

Redis is already configured in `docker/php/php.ini`:

```ini
session.save_handler = redis
session.save_path = "tcp://redis:6379"
```

### Test Redis from PHP

Create `scripts/test-redis.php`:

```php
<?php
$redis = new Redis();
$redis->connect(getenv('REDIS_HOST') ?: 'redis', 6379);

// Test set/get
$redis->set('test_key', 'Hello Redis!');
$value = $redis->get('test_key');

echo "Redis test: {$value}\n";

// Test session
session_start();
$_SESSION['test'] = 'Session works!';
echo "Session test: " . $_SESSION['test'] . "\n";
```

Run:

```bash
docker-compose exec php php /var/www/html/scripts/test-redis.php
```

## Step 7: Background Workers Configuration

### Task Worker Setup

The task worker runs background jobs (email queue, scheduled tasks, etc.).

Update `TaskWorker/worker.php` if needed for your configuration:

```php
<?php
require_once __DIR__ . '/../sections/globalConfig.php';

// Worker configuration
$sleepTime = (int)getenv('WORKER_SLEEP_TIME') ?: 5;
$maxMemory = getenv('MAX_WORKER_MEMORY') ?: '512M';

ini_set('memory_limit', $maxMemory);

// Main worker loop
while (true) {
    try {
        // Process email queue
        processEmailQueue();
        
        // Process scheduled tasks
        processScheduledTasks();
        
        // Clean up old data
        cleanupOldData();
        
    } catch (Exception $e) {
        error_log("Worker error: " . $e->getMessage());
    }
    
    sleep($sleepTime);
}

function processEmailQueue() {
    // Implementation in TaskWorker/
}

function processScheduledTasks() {
    // Implementation in TaskWorker/
}

function cleanupOldData() {
    // Implementation in TaskWorker/
}
```

### Mail Notification Service

Configure `mailNotify/notify.php`:

```php
<?php
require_once __DIR__ . '/../sections/globalConfig.php';

// SendinBlue configuration (if using)
if (getenv('SENDINBLUE_API_KEY')) {
    $sendinblue = new \SendinBlue\Client\Api\TransactionalEmailsApi(
        new GuzzleHttp\Client(),
        \SendinBlue\Client\Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', getenv('SENDINBLUE_API_KEY'))
    );
}

// Process email notifications
// Implementation...
```

## Step 8: Composer Dependencies

### Install PHP Dependencies

```bash
# Install API dependencies
docker-compose exec php composer install --working-dir=/var/www/html/sections/api/include --no-dev --optimize-autoloader

# Install TaskWorker dependencies
docker-compose exec php composer install --working-dir=/var/www/html/TaskWorker/include --no-dev --optimize-autoloader

# Install mailNotify dependencies
docker-compose exec php composer install --working-dir=/var/www/html/mailNotify/include --no-dev --optimize-autoloader
```

### Production Optimization

```bash
# Generate optimized autoload files
docker-compose exec php composer dump-autoload --optimize --no-dev

# Clear cache
docker-compose exec php php -r "opcache_reset();"
```

## Step 9: File Permissions

### Set Proper Permissions

```bash
# From WSL/Linux
cd /path/to/TravianT4.6

# Set ownership (Docker user is 1000:1000)
sudo chown -R 1000:1000 .

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Make scripts executable
chmod +x scripts/*.sh

# Writable directories
chmod -R 775 sections/servers/*/logs
chmod -R 775 storage/logs
chmod -R 775 storage/cache
```

## Step 10: Verify Configuration

### Configuration Checklist

Create `scripts/verify-config.php`:

```php
<?php
require_once __DIR__ . '/../sections/globalConfig.php';

echo "Configuration Verification\n";
echo "==========================\n\n";

// Database
echo "Database:\n";
echo "  Host: " . DB_HOST . "\n";
echo "  Database: " . DB_DATABASE . "\n";
echo "  Connection: ";
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE,
        DB_USERNAME,
        DB_PASSWORD
    );
    echo "✓ OK\n";
} catch (PDOException $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

// Redis
echo "\nRedis:\n";
echo "  Host: " . REDIS_HOST . "\n";
echo "  Connection: ";
try {
    $redis = new Redis();
    $redis->connect(REDIS_HOST, REDIS_PORT);
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

// SMTP
echo "\nSMTP:\n";
echo "  Host: " . SMTP_HOST . "\n";
echo "  Port: " . SMTP_PORT . "\n";
echo "  Username: " . SMTP_USERNAME . "\n";

// reCAPTCHA
echo "\nreCAPTCHA:\n";
echo "  Site Key: " . (RECAPTCHA_SITE_KEY ? "✓ Set" : "✗ Not set") . "\n";
echo "  Secret Key: " . (RECAPTCHA_SECRET_KEY ? "✓ Set" : "✗ Not set") . "\n";

// Directories
echo "\nWritable Directories:\n";
$dirs = [
    'sections/servers/testworld/logs',
    'sections/servers/demo/logs',
    'storage/logs',
    'storage/cache',
];

foreach ($dirs as $dir) {
    $path = __DIR__ . '/../' . $dir;
    echo "  {$dir}: ";
    if (is_writable($path)) {
        echo "✓ Writable\n";
    } else {
        echo "✗ Not writable\n";
    }
}

echo "\n";
```

Run verification:

```bash
docker-compose exec php php /var/www/html/scripts/verify-config.php
```

## Next Steps

Continue to [05-GIT-GITHUB-WORKFLOW.md](05-GIT-GITHUB-WORKFLOW.md) for version control and CI/CD setup.

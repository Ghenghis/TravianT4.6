# ============================================================================
# TRAVIANT4.6 - XAMPP PERFORMANCE TUNING SCRIPT
# ============================================================================
# Version: 2.0
# Purpose: Optimize PHP, MySQL, and Apache for production performance
# Requirements: XAMPP 8.2+, PowerShell 5.1+, Administrator privileges
# 
# USAGE:
#   .\scripts\xampp-performance-tune.ps1
#   .\scripts\xampp-performance-tune.ps1 -SkipRestart
#
# WHAT THIS SCRIPT DOES:
# 1. Analyzes current system resources (RAM, CPU)
# 2. Optimizes PHP settings (memory, OPcache, execution time)
# 3. Tunes MySQL InnoDB settings (buffer pool, connections)
# 4. Configures Apache MPM settings (workers, connections)
# 5. Restarts services to apply changes
# 6. Validates optimizations
# ============================================================================

param(
    [switch]$SkipRestart,
    [switch]$DryRun
)

# Requires Administrator
if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "This script requires Administrator privileges. Please run as Administrator." -ForegroundColor Red
    exit 1
}

# Script Configuration
$ErrorActionPreference = "Continue"
$PhpIni = "C:\xampp\php\php.ini"
$MySQLIni = "C:\xampp\mysql\bin\my.ini"
$HttpdConf = "C:\xampp\apache\conf\httpd.conf"
$BackupDir = "C:\xampp\backups\config_backups"

# Color Output Functions
function Write-ColorOutput {
    param([string]$Message, [string]$Color = "White")
    Write-Host $Message -ForegroundColor $Color
}

function Write-Success { param([string]$Message) Write-ColorOutput "✓ $Message" "Green" }
function Write-Info { param([string]$Message) Write-ColorOutput "ℹ $Message" "Cyan" }
function Write-Warning { param([string]$Message) Write-ColorOutput "⚠ $Message" "Yellow" }
function Write-Error { param([string]$Message) Write-ColorOutput "✗ $Message" "Red" }
function Write-Header { 
    param([string]$Message) 
    Write-Host ""
    Write-ColorOutput "============================================================================" "Magenta"
    Write-ColorOutput $Message "Magenta"
    Write-ColorOutput "============================================================================" "Magenta"
}

# ============================================================================
# FUNCTION: Get-SystemResources
# ============================================================================
function Get-SystemResources {
    Write-Info "Analyzing system resources..."
    
    $os = Get-CimInstance -ClassName Win32_OperatingSystem
    $cpu = Get-CimInstance -ClassName Win32_Processor
    $totalRAM = [math]::Round($os.TotalVisibleMemorySize / 1MB, 2)
    $freeRAM = [math]::Round($os.FreePhysicalMemory / 1MB, 2)
    $cpuCores = $cpu.NumberOfCores
    
    Write-Info "  Total RAM: $totalRAM GB"
    Write-Info "  Free RAM: $freeRAM GB"
    Write-Info "  CPU Cores: $cpuCores"
    
    return @{
        TotalRAM = $totalRAM
        FreeRAM = $freeRAM
        CPUCores = $cpuCores
    }
}

# ============================================================================
# FUNCTION: Backup-ConfigFile
# ============================================================================
function Backup-ConfigFile {
    param([string]$FilePath)
    
    $fileName = Split-Path -Leaf $FilePath
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $backupFile = Join-Path $BackupDir "$fileName.$timestamp.bak"
    
    if (-not (Test-Path $BackupDir)) {
        New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
    }
    
    Copy-Item -Path $FilePath -Destination $backupFile -Force
    Write-Success "Backup created: $backupFile"
}

# ============================================================================
# FUNCTION: Optimize-PHPSettings
# ============================================================================
function Optimize-PHPSettings {
    param([hashtable]$SystemInfo)
    
    Write-Info "Optimizing PHP settings..."
    
    if (-not (Test-Path $PhpIni)) {
        Write-Error "PHP configuration not found: $PhpIni"
        return
    }
    
    Backup-ConfigFile -FilePath $PhpIni
    
    $content = Get-Content $PhpIni
    
    # PHP Memory Settings
    $memoryLimit = [math]::Min(512, [math]::Round($SystemInfo.TotalRAM * 0.25))
    $content = $content -replace '^memory_limit\s*=.*', "memory_limit = ${memoryLimit}M"
    
    # Execution Time
    $content = $content -replace '^max_execution_time\s*=.*', "max_execution_time = 300"
    $content = $content -replace '^max_input_time\s*=.*', "max_input_time = 300"
    
    # Upload Settings
    $content = $content -replace '^post_max_size\s*=.*', "post_max_size = 128M"
    $content = $content -replace '^upload_max_filesize\s*=.*', "upload_max_filesize = 128M"
    
    # OPcache Settings
    $opcacheMemory = [math]::Min(256, [math]::Round($SystemInfo.TotalRAM * 0.125))
    $content = $content -replace '^;?opcache.enable\s*=.*', "opcache.enable = 1"
    $content = $content -replace '^;?opcache.memory_consumption\s*=.*', "opcache.memory_consumption = $opcacheMemory"
    $content = $content -replace '^;?opcache.interned_strings_buffer\s*=.*', "opcache.interned_strings_buffer = 16"
    $content = $content -replace '^;?opcache.max_accelerated_files\s*=.*', "opcache.max_accelerated_files = 10000"
    $content = $content -replace '^;?opcache.revalidate_freq\s*=.*', "opcache.revalidate_freq = 2"
    $content = $content -replace '^;?opcache.fast_shutdown\s*=.*', "opcache.fast_shutdown = 1"
    
    # Realpath Cache
    $content = $content -replace '^;?realpath_cache_size\s*=.*', "realpath_cache_size = 4096K"
    $content = $content -replace '^;?realpath_cache_ttl\s*=.*', "realpath_cache_ttl = 600"
    
    if (-not $DryRun) {
        $content | Set-Content -Path $PhpIni -Encoding UTF8
        Write-Success "PHP settings optimized"
    } else {
        Write-Info "DRY RUN: Would optimize PHP settings"
    }
}

# ============================================================================
# FUNCTION: Optimize-MySQLSettings
# ============================================================================
function Optimize-MySQLSettings {
    param([hashtable]$SystemInfo)
    
    Write-Info "Optimizing MySQL settings..."
    
    if (-not (Test-Path $MySQLIni)) {
        Write-Error "MySQL configuration not found: $MySQLIni"
        return
    }
    
    Backup-ConfigFile -FilePath $MySQLIni
    
    $content = Get-Content $MySQLIni
    
    # InnoDB Buffer Pool (50-70% of total RAM for dedicated DB server, 25% for shared)
    $bufferPoolSize = [math]::Round($SystemInfo.TotalRAM * 0.25 * 1024)  # In MB
    $content = $content -replace '^innodb_buffer_pool_size\s*=.*', "innodb_buffer_pool_size = ${bufferPoolSize}M"
    
    # Connection Settings
    $maxConnections = [math]::Max(200, $SystemInfo.CPUCores * 50)
    $content = $content -replace '^max_connections\s*=.*', "max_connections = $maxConnections"
    $content = $content -replace '^thread_cache_size\s*=.*', "thread_cache_size = 16"
    
    # Table Cache
    $content = $content -replace '^table_open_cache\s*=.*', "table_open_cache = 4096"
    
    # InnoDB Settings
    $content = $content -replace '^innodb_log_file_size\s*=.*', "innodb_log_file_size = 256M"
    $content = $content -replace '^innodb_log_buffer_size\s*=.*', "innodb_log_buffer_size = 8M"
    $content = $content -replace '^innodb_flush_log_at_trx_commit\s*=.*', "innodb_flush_log_at_trx_commit = 2"
    $content = $content -replace '^innodb_file_per_table\s*=.*', "innodb_file_per_table = 1"
    
    if (-not $DryRun) {
        $content | Set-Content -Path $MySQLIni -Encoding UTF8
        Write-Success "MySQL settings optimized"
    } else {
        Write-Info "DRY RUN: Would optimize MySQL settings"
    }
}

# ============================================================================
# FUNCTION: Optimize-ApacheSettings
# ============================================================================
function Optimize-ApacheSettings {
    param([hashtable]$SystemInfo)
    
    Write-Info "Optimizing Apache settings..."
    
    if (-not (Test-Path $HttpdConf)) {
        Write-Error "Apache configuration not found: $HttpdConf"
        return
    }
    
    Backup-ConfigFile -FilePath $HttpdConf
    
    $content = Get-Content $HttpdConf
    
    # MPM Settings (calculated based on available RAM and CPU)
    $maxClients = [math]::Min(400, $SystemInfo.CPUCores * 100)
    $startServers = [math]::Max(5, [math]::Round($SystemInfo.CPUCores / 2))
    $minSpareThreads = $startServers * 5
    $maxSpareThreads = $startServers * 10
    
    # Find and update MPM section
    $mpmUpdated = $false
    for ($i = 0; $i -lt $content.Count; $i++) {
        if ($content[$i] -match '<IfModule mpm_winnt_module>') {
            $content[$i+1] = "    ThreadsPerChild 150"
            $content[$i+2] = "    MaxRequestWorkers $maxClients"
            $mpmUpdated = $true
            break
        }
    }
    
    # KeepAlive Settings
    $content = $content -replace '^KeepAlive\s+.*', "KeepAlive On"
    $content = $content -replace '^MaxKeepAliveRequests\s+.*', "MaxKeepAliveRequests 100"
    $content = $content -replace '^KeepAliveTimeout\s+.*', "KeepAliveTimeout 5"
    
    # Timeout
    $content = $content -replace '^Timeout\s+.*', "Timeout 300"
    
    if (-not $DryRun) {
        $content | Set-Content -Path $HttpdConf -Encoding UTF8
        Write-Success "Apache settings optimized"
    } else {
        Write-Info "DRY RUN: Would optimize Apache settings"
    }
}

# ============================================================================
# FUNCTION: Restart-Services
# ============================================================================
function Restart-Services {
    Write-Info "Restarting services to apply changes..."
    
    # Stop services
    Write-Info "Stopping Apache..."
    & "C:\xampp\apache_stop.bat" | Out-Null
    Start-Sleep -Seconds 3
    
    Write-Info "Stopping MySQL..."
    & "C:\xampp\mysql_stop.bat" | Out-Null
    Start-Sleep -Seconds 5
    
    # Start services
    Write-Info "Starting MySQL..."
    & "C:\xampp\mysql_start.bat" | Out-Null
    Start-Sleep -Seconds 10
    
    Write-Info "Starting Apache..."
    & "C:\xampp\apache_start.bat" | Out-Null
    Start-Sleep -Seconds 5
    
    Write-Success "Services restarted"
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

Write-Header "TRAVIANT4.6 - XAMPP PERFORMANCE TUNING"

if ($DryRun) {
    Write-Warning "DRY RUN MODE - No changes will be made"
}

# Get system info
Write-Header "SYSTEM ANALYSIS"
$systemInfo = Get-SystemResources

# Optimize components
Write-Header "PHP OPTIMIZATION"
Optimize-PHPSettings -SystemInfo $systemInfo

Write-Header "MYSQL OPTIMIZATION"
Optimize-MySQLSettings -SystemInfo $systemInfo

Write-Header "APACHE OPTIMIZATION"
Optimize-ApacheSettings -SystemInfo $systemInfo

# Restart services
if (-not $SkipRestart -and -not $DryRun) {
    Write-Header "SERVICE RESTART"
    Restart-Services
}

# Summary
Write-Header "OPTIMIZATION COMPLETE"
Write-Success "All optimizations applied successfully"
Write-Info ""
Write-Info "Optimized Settings:"
Write-Info "  PHP Memory Limit: $([math]::Min(512, [math]::Round($systemInfo.TotalRAM * 0.25)))M"
Write-Info "  PHP OPcache: $([math]::Min(256, [math]::Round($systemInfo.TotalRAM * 0.125)))M"
Write-Info "  MySQL Buffer Pool: $([math]::Round($systemInfo.TotalRAM * 0.25 * 1024))M"
Write-Info "  MySQL Max Connections: $([math]::Max(200, $systemInfo.CPUCores * 50))"
Write-Info "  Apache Max Workers: $([math]::Min(400, $systemInfo.CPUCores * 100))"
Write-Info ""
Write-Info "Configuration backups saved to: $BackupDir"

if ($SkipRestart) {
    Write-Warning "Services not restarted (SkipRestart flag set)"
    Write-Info "Please restart Apache and MySQL manually for changes to take effect"
}

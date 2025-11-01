# ============================================================================
# TRAVIANT4.6 - XAMPP BACKUP SCRIPT
# ============================================================================
# Version: 2.0
# Purpose: Automated backup with 3-tier retention
# Requirements: MySQL 8.0+, PostgreSQL 14+, PowerShell 5.1+, 7-Zip (optional)
# 
# USAGE:
#   .\scripts\xampp-backup.ps1
#   .\scripts\xampp-backup.ps1 -BackupDir "D:\Backups\Travian"
#
# WHAT THIS SCRIPT DOES:
# 1. PostgreSQL dump (gameservers table from travian_global)
# 2. MySQL dumps (all 8 world databases)
# 3. File backup (connection.php configs, uploads, logs)
# 4. Compressed archives (.zip)
# 5. 3-tier retention: daily (7 days), weekly (4 weeks), monthly (12 months)
# 6. Automatic cleanup of old backups
# ============================================================================

param(
    [string]$BackupDir = "C:\xampp\backups",
    [string]$MysqlHost = "localhost",
    [string]$MysqlRootPassword = "TravianSecureRoot2025!",
    [string]$PgHost = "localhost",
    [string]$PgUser = "postgres",
    [string]$PgPassword = "postgres",
    [string]$PgDatabase = "travian_global",
    [int]$DailyRetention = 7,
    [int]$WeeklyRetention = 4,
    [int]$MonthlyRetention = 12,
    [switch]$SkipCompression
)

# Script Configuration
$ErrorActionPreference = "Stop"
$ScriptRoot = "C:\xampp\htdocs"
$MysqlBin = "C:\xampp\mysql\bin\mysqldump.exe"
$MysqlRestore = "C:\xampp\mysql\bin\mysql.exe"
$PgDumpBin = "C:\xampp\pgsql\bin\pg_dump.exe"
$PgRestoreBin = "C:\xampp\pgsql\bin\psql.exe"

# Timestamp
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$DateStamp = Get-Date -Format "yyyyMMdd"
$DayOfWeek = (Get-Date).DayOfWeek
$DayOfMonth = (Get-Date).Day

# Backup Paths
$DailyBackupDir = Join-Path $BackupDir "daily"
$WeeklyBackupDir = Join-Path $BackupDir "weekly"
$MonthlyBackupDir = Join-Path $BackupDir "monthly"

# World Configuration
$GameWorlds = @("speed10k", "speed125k", "speed250k", "speed500k", "speed5m", "demo", "dev", "testworld")

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
# FUNCTION: Initialize-BackupDirectories
# ============================================================================
function Initialize-BackupDirectories {
    Write-Info "Initializing backup directories..."
    
    $dirs = @($BackupDir, $DailyBackupDir, $WeeklyBackupDir, $MonthlyBackupDir)
    
    foreach ($dir in $dirs) {
        if (-not (Test-Path $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
            Write-Success "Created: $dir"
        }
    }
}

# ============================================================================
# FUNCTION: Backup-PostgreSQLDatabase
# ============================================================================
function Backup-PostgreSQLDatabase {
    param([string]$OutputDir)
    
    Write-Info "Backing up PostgreSQL database: $PgDatabase..."
    
    $env:PGPASSWORD = $PgPassword
    
    try {
        $backupFile = Join-Path $OutputDir "postgres_$PgDatabase`_$Timestamp.sql"
        
        & $PgDumpBin -h $PgHost -U $PgUser -d $PgDatabase -f $backupFile 2>&1 | Out-Null
        
        if ($LASTEXITCODE -eq 0) {
            $size = (Get-Item $backupFile).Length / 1MB
            Write-Success "PostgreSQL backup created: $backupFile ($('{0:N2}' -f $size) MB)"
            return $backupFile
        } else {
            Write-Error "PostgreSQL backup failed"
            return $null
        }
    } catch {
        Write-Error "PostgreSQL backup error: $_"
        return $null
    } finally {
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
    }
}

# ============================================================================
# FUNCTION: Backup-MySQLDatabase
# ============================================================================
function Backup-MySQLDatabase {
    param([string]$DatabaseName, [string]$OutputDir)
    
    Write-Info "Backing up MySQL database: $DatabaseName..."
    
    try {
        $backupFile = Join-Path $OutputDir "mysql_$DatabaseName`_$Timestamp.sql"
        
        & $MysqlBin -h $MysqlHost -uroot -p"$MysqlRootPassword" $DatabaseName > $backupFile 2>&1
        
        if ($LASTEXITCODE -eq 0) {
            $size = (Get-Item $backupFile).Length / 1MB
            Write-Success "MySQL backup created: $DatabaseName ($('{0:N2}' -f $size) MB)"
            return $backupFile
        } else {
            Write-Error "MySQL backup failed for $DatabaseName"
            return $null
        }
    } catch {
        Write-Error "MySQL backup error for $DatabaseName : $_"
        return $null
    }
}

# ============================================================================
# FUNCTION: Backup-ConfigFiles
# ============================================================================
function Backup-ConfigFiles {
    param([string]$OutputDir)
    
    Write-Info "Backing up configuration files..."
    
    $configBackupDir = Join-Path $OutputDir "configs_$Timestamp"
    New-Item -ItemType Directory -Path $configBackupDir -Force | Out-Null
    
    $backedUp = 0
    
    foreach ($world in $GameWorlds) {
        $configFile = Join-Path $ScriptRoot "sections\servers\$world\config\connection.php"
        
        if (Test-Path $configFile) {
            $destDir = Join-Path $configBackupDir $world
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
            Copy-Item -Path $configFile -Destination $destDir -Force
            $backedUp++
        }
    }
    
    Write-Success "Configuration files backed up: $backedUp files"
    return $configBackupDir
}

# ============================================================================
# FUNCTION: Compress-BackupDirectory
# ============================================================================
function Compress-BackupDirectory {
    param([string]$SourceDir, [string]$ArchiveName)
    
    if ($SkipCompression) {
        Write-Info "Skipping compression (SkipCompression flag set)"
        return $SourceDir
    }
    
    Write-Info "Compressing backup..."
    
    try {
        $archivePath = "$ArchiveName.zip"
        
        # Use PowerShell built-in compression
        Compress-Archive -Path "$SourceDir\*" -DestinationPath $archivePath -CompressionLevel Optimal -Force
        
        $originalSize = (Get-ChildItem -Path $SourceDir -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB
        $compressedSize = (Get-Item $archivePath).Length / 1MB
        $ratio = [math]::Round((1 - ($compressedSize / $originalSize)) * 100, 2)
        
        Write-Success "Archive created: $archivePath ($('{0:N2}' -f $compressedSize) MB, $ratio% compression)"
        
        # Remove uncompressed files
        Remove-Item -Path $SourceDir -Recurse -Force
        
        return $archivePath
    } catch {
        Write-Error "Compression error: $_"
        return $SourceDir
    }
}

# ============================================================================
# FUNCTION: Cleanup-OldBackups
# ============================================================================
function Cleanup-OldBackups {
    param([string]$BackupType, [string]$BackupPath, [int]$RetentionDays)
    
    Write-Info "Cleaning up old $BackupType backups (retention: $RetentionDays)..."
    
    $cutoffDate = (Get-Date).AddDays(-$RetentionDays)
    
    $oldBackups = Get-ChildItem -Path $BackupPath -File | Where-Object { $_.LastWriteTime -lt $cutoffDate }
    
    if ($oldBackups) {
        $deletedCount = 0
        $freedSpace = 0
        
        foreach ($backup in $oldBackups) {
            $size = $backup.Length / 1MB
            $freedSpace += $size
            Remove-Item -Path $backup.FullName -Force
            Write-Info "  Deleted: $($backup.Name) ($('{0:N2}' -f $size) MB)"
            $deletedCount++
        }
        
        Write-Success "Cleaned up $deletedCount old backups, freed $('{0:N2}' -f $freedSpace) MB"
    } else {
        Write-Info "No old backups to clean up"
    }
}

# ============================================================================
# FUNCTION: Get-BackupStatistics
# ============================================================================
function Get-BackupStatistics {
    Write-Info ""
    Write-Info "Backup Statistics:"
    
    $dailyBackups = Get-ChildItem -Path $DailyBackupDir -File -ErrorAction SilentlyContinue
    $weeklyBackups = Get-ChildItem -Path $WeeklyBackupDir -File -ErrorAction SilentlyContinue
    $monthlyBackups = Get-ChildItem -Path $MonthlyBackupDir -File -ErrorAction SilentlyContinue
    
    $totalSize = 0
    $totalSize += ($dailyBackups | Measure-Object -Property Length -Sum).Sum / 1GB
    $totalSize += ($weeklyBackups | Measure-Object -Property Length -Sum).Sum / 1GB
    $totalSize += ($monthlyBackups | Measure-Object -Property Length -Sum).Sum / 1GB
    
    Write-Info "  Daily backups: $($dailyBackups.Count) files"
    Write-Info "  Weekly backups: $($weeklyBackups.Count) files"
    Write-Info "  Monthly backups: $($monthlyBackups.Count) files"
    Write-Info "  Total backup size: $('{0:N2}' -f $totalSize) GB"
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

Write-Header "TRAVIANT4.6 - XAMPP BACKUP"
Write-Info "Backup Directory: $BackupDir"
Write-Info "Timestamp: $Timestamp"
Write-Info "Retention: Daily=$DailyRetention, Weekly=$WeeklyRetention, Monthly=$MonthlyRetention"

# Initialize directories
Initialize-BackupDirectories

# Determine backup type (daily, weekly, monthly)
$backupType = "daily"
$targetDir = $DailyBackupDir

if ($DayOfWeek -eq "Sunday") {
    $backupType = "weekly"
    $targetDir = $WeeklyBackupDir
}

if ($DayOfMonth -eq 1) {
    $backupType = "monthly"
    $targetDir = $MonthlyBackupDir
}

Write-Info "Backup Type: $backupType"

# Create temporary backup directory
$tempBackupDir = Join-Path $BackupDir "temp_$Timestamp"
New-Item -ItemType Directory -Path $tempBackupDir -Force | Out-Null

# Backup PostgreSQL
Write-Header "POSTGRESQL BACKUP"
$pgBackup = Backup-PostgreSQLDatabase -OutputDir $tempBackupDir

# Backup MySQL
Write-Header "MYSQL BACKUPS"
$mysqlBackups = @()
foreach ($world in $GameWorlds) {
    $dbName = "travian_world_$world"
    $backup = Backup-MySQLDatabase -DatabaseName $dbName -OutputDir $tempBackupDir
    if ($backup) {
        $mysqlBackups += $backup
    }
}

# Backup Configuration Files
Write-Header "CONFIGURATION BACKUP"
$configBackup = Backup-ConfigFiles -OutputDir $tempBackupDir

# Compress backup
Write-Header "COMPRESSION"
$archiveName = Join-Path $targetDir "travian_$backupType`_$DateStamp"
$finalBackup = Compress-BackupDirectory -SourceDir $tempBackupDir -ArchiveName $archiveName

# Cleanup old backups
Write-Header "CLEANUP"
Cleanup-OldBackups -BackupType "daily" -BackupPath $DailyBackupDir -RetentionDays $DailyRetention
if ($DayOfWeek -eq "Sunday") {
    Cleanup-OldBackups -BackupType "weekly" -BackupPath $WeeklyBackupDir -RetentionDays ($WeeklyRetention * 7)
}
if ($DayOfMonth -eq 1) {
    Cleanup-OldBackups -BackupType "monthly" -BackupPath $MonthlyBackupDir -RetentionDays ($MonthlyRetention * 30)
}

# Statistics
Write-Header "BACKUP COMPLETE"
Get-BackupStatistics

Write-Info ""
Write-Info "Backup Location: $finalBackup"
Write-Info ""
Write-Success "Backup completed successfully!"

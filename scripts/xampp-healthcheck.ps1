# ============================================================================
# TRAVIANT4.6 - XAMPP HEALTH CHECK SCRIPT
# ============================================================================
# Version: 2.0
# Purpose: Continuous monitoring and health checking
# Requirements: XAMPP 8.2+, PowerShell 5.1+
# 
# USAGE:
#   .\scripts\xampp-healthcheck.ps1
#   .\scripts\xampp-healthcheck.ps1 -Continuous -IntervalMinutes 5
#   .\scripts\xampp-healthcheck.ps1 -SendAlerts -EmailTo "admin@example.com"
#
# WHAT THIS SCRIPT MONITORS:
# 1. Service status (Apache, MySQL, PostgreSQL)
# 2. Disk space (C:\ drive, alert if <10GB free)
# 3. Database connectivity (MySQL, PostgreSQL)
# 4. API endpoint health (GET /v1/servers/loadServers)
# 5. Process uptime tracking
# 6. Log file sizes (alert if >100MB)
# 7. CPU and memory usage
# ============================================================================

param(
    [string]$MysqlHost = "localhost",
    [string]$MysqlUser = "travian",
    [string]$MysqlPassword = "TravianDB2025!",
    [string]$PgHost = "localhost",
    [string]$PgUser = "postgres",
    [string]$PgPassword = "postgres",
    [string]$PgDatabase = "travian_global",
    [string]$ApiUrl = "http://localhost",
    [switch]$Continuous,
    [int]$IntervalMinutes = 5,
    [switch]$SendAlerts,
    [string]$EmailTo = "",
    [int]$DiskSpaceWarningGB = 10
)

# Script Configuration
$ErrorActionPreference = "Continue"
$MysqlBin = "C:\xampp\mysql\bin\mysql.exe"
$PsqlBin = "C:\xampp\pgsql\bin\psql.exe"
$LogFile = "C:\xampp\htdocs\logs\healthcheck_$(Get-Date -Format 'yyyyMMdd').log"

# Health Check Results
$HealthStatus = @{
    Timestamp = Get-Date
    Overall = $true
    Checks = @{}
}

# Color Output Functions
function Write-ColorOutput {
    param([string]$Message, [string]$Color = "White")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage -ForegroundColor $Color
    Add-Content -Path $LogFile -Value $logMessage -ErrorAction SilentlyContinue
}

function Write-Success { param([string]$Message) Write-ColorOutput "✓ $Message" "Green" }
function Write-Fail { param([string]$Message) Write-ColorOutput "✗ $Message" "Red"; $script:HealthStatus.Overall = $false }
function Write-Info { param([string]$Message) Write-ColorOutput "ℹ $Message" "Cyan" }
function Write-Warning { param([string]$Message) Write-ColorOutput "⚠ $Message" "Yellow" }
function Write-Header { 
    param([string]$Message) 
    Write-Host ""
    Write-ColorOutput "============================================================================" "Magenta"
    Write-ColorOutput $Message "Magenta"
    Write-ColorOutput "============================================================================" "Magenta"
}

# ============================================================================
# HEALTH CHECK FUNCTIONS
# ============================================================================

function Test-ServiceHealth {
    param([string]$ServiceName, [string]$ProcessName)
    
    $checkName = "Service_$ServiceName"
    
    $process = Get-Process -Name $ProcessName -ErrorAction SilentlyContinue
    
    if ($process) {
        $uptime = (Get-Date) - $process.StartTime
        $uptimeStr = "{0:dd}d {0:hh}h {0:mm}m" -f $uptime
        
        Write-Success "$ServiceName is running (PID: $($process.Id), Uptime: $uptimeStr)"
        $HealthStatus.Checks[$checkName] = @{
            Status = "OK"
            PID = $process.Id
            Uptime = $uptimeStr
            CPU = $process.CPU
            Memory = [math]::Round($process.WorkingSet64 / 1MB, 2)
        }
        return $true
    } else {
        Write-Fail "$ServiceName is NOT running"
        $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = "Process not found" }
        return $false
    }
}

function Test-DiskSpace {
    $drive = Get-PSDrive -Name C
    $freeSpaceGB = [math]::Round($drive.Free / 1GB, 2)
    $totalSpaceGB = [math]::Round($drive.Used / 1GB + $freeSpaceGB, 2)
    $usedPercent = [math]::Round(($drive.Used / ($drive.Used + $drive.Free)) * 100, 2)
    
    $checkName = "DiskSpace_C"
    
    if ($freeSpaceGB -lt $DiskSpaceWarningGB) {
        Write-Warning "Low disk space on C:\ - $freeSpaceGB GB free (Used: $usedPercent%)"
        $HealthStatus.Checks[$checkName] = @{
            Status = "WARNING"
            FreeGB = $freeSpaceGB
            TotalGB = $totalSpaceGB
            UsedPercent = $usedPercent
        }
    } else {
        Write-Success "Disk space OK - $freeSpaceGB GB free (Used: $usedPercent%)"
        $HealthStatus.Checks[$checkName] = @{
            Status = "OK"
            FreeGB = $freeSpaceGB
            TotalGB = $totalSpaceGB
            UsedPercent = $usedPercent
        }
    }
}

function Test-MySQLConnection {
    $checkName = "MySQL_Connection"
    
    try {
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e "SELECT 1;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "MySQL connection OK"
            $HealthStatus.Checks[$checkName] = @{ Status = "OK" }
            return $true
        } else {
            Write-Fail "MySQL connection failed"
            $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = $result }
            return $false
        }
    } catch {
        Write-Fail "MySQL connection error: $_"
        $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = $_.Exception.Message }
        return $false
    }
}

function Test-PostgreSQLConnection {
    $checkName = "PostgreSQL_Connection"
    $env:PGPASSWORD = $PgPassword
    
    try {
        $result = & $PsqlBin -h $PgHost -U $PgUser -d $PgDatabase -c "SELECT 1;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "PostgreSQL connection OK"
            $HealthStatus.Checks[$checkName] = @{ Status = "OK" }
            return $true
        } else {
            Write-Fail "PostgreSQL connection failed"
            $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = $result }
            return $false
        }
    } catch {
        Write-Fail "PostgreSQL connection error: $_"
        $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = $_.Exception.Message }
        return $false
    } finally {
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
    }
}

function Test-ApiEndpoint {
    param([string]$Endpoint = "/v1/servers/loadServers")
    
    $checkName = "API_$($Endpoint -replace '/',  '_')"
    $url = "$ApiUrl$Endpoint"
    
    try {
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 10
        
        if ($response.StatusCode -eq 200) {
            $responseTime = $response.Headers['X-Response-Time']
            Write-Success "API endpoint OK: $Endpoint (Status: 200)"
            $HealthStatus.Checks[$checkName] = @{ 
                Status = "OK"
                StatusCode = 200
                ResponseTime = $responseTime
            }
            return $true
        } else {
            Write-Fail "API endpoint returned status: $($response.StatusCode)"
            $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; StatusCode = $response.StatusCode }
            return $false
        }
    } catch {
        Write-Fail "API endpoint failed: $Endpoint - $_"
        $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = $_.Exception.Message }
        return $false
    }
}

function Test-LogFileSizes {
    $logFiles = @(
        "C:\xampp\apache\logs\error.log",
        "C:\xampp\apache\logs\access.log",
        "C:\xampp\php\logs\php_error.log",
        "C:\xampp\mysql\data\*.err"
    )
    
    foreach ($logPath in $logFiles) {
        $files = Get-Item -Path $logPath -ErrorAction SilentlyContinue
        
        foreach ($file in $files) {
            $sizeMB = [math]::Round($file.Length / 1MB, 2)
            $checkName = "LogSize_$($file.Name)"
            
            if ($sizeMB -gt 100) {
                Write-Warning "Large log file: $($file.Name) - $sizeMB MB"
                $HealthStatus.Checks[$checkName] = @{ Status = "WARNING"; SizeMB = $sizeMB }
            } else {
                Write-Info "Log file OK: $($file.Name) - $sizeMB MB"
                $HealthStatus.Checks[$checkName] = @{ Status = "OK"; SizeMB = $sizeMB }
            }
        }
    }
}

function Send-HealthAlert {
    if (-not $SendAlerts -or -not $EmailTo) {
        return
    }
    
    # This is a placeholder - implement email sending based on your mail server
    # You can use Send-MailMessage or external SMTP services
    
    $subject = "XAMPP Health Check Alert - $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
    $body = "Health check failed. See attached log for details."
    
    Write-Info "Would send alert email to: $EmailTo"
    # Send-MailMessage -To $EmailTo -Subject $subject -Body $body -SmtpServer "your-smtp-server"
}

function Show-HealthSummary {
    Write-Header "HEALTH CHECK SUMMARY"
    
    $totalChecks = $HealthStatus.Checks.Count
    $okChecks = ($HealthStatus.Checks.Values | Where-Object { $_.Status -eq "OK" }).Count
    $warningChecks = ($HealthStatus.Checks.Values | Where-Object { $_.Status -eq "WARNING" }).Count
    $failedChecks = ($HealthStatus.Checks.Values | Where-Object { $_.Status -eq "FAIL" }).Count
    
    Write-Info "Total Checks: $totalChecks"
    Write-ColorOutput "OK: $okChecks" "Green"
    Write-ColorOutput "WARNING: $warningChecks" "Yellow"
    Write-ColorOutput "FAILED: $failedChecks" "Red"
    
    if ($HealthStatus.Overall) {
        Write-ColorOutput "`n✓ ALL SYSTEMS OPERATIONAL" "Green"
    } else {
        Write-ColorOutput "`n✗ SYSTEM HEALTH ISSUES DETECTED" "Red"
        Send-HealthAlert
    }
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

do {
    Write-Header "TRAVIANT4.6 - XAMPP HEALTH CHECK"
    Write-Info "Check Time: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    
    # Service Health
    Write-Header "SERVICE STATUS"
    Test-ServiceHealth -ServiceName "Apache" -ProcessName "httpd"
    Test-ServiceHealth -ServiceName "MySQL" -ProcessName "mysqld"
    Test-ServiceHealth -ServiceName "PostgreSQL" -ProcessName "postgres"
    
    # Disk Space
    Write-Header "DISK SPACE"
    Test-DiskSpace
    
    # Database Connections
    Write-Header "DATABASE CONNECTIONS"
    Test-MySQLConnection
    Test-PostgreSQLConnection
    
    # API Endpoints
    Write-Header "API ENDPOINTS"
    Test-ApiEndpoint -Endpoint "/v1/servers/loadServers"
    
    # Log Files
    Write-Header "LOG FILE SIZES"
    Test-LogFileSizes
    
    # Summary
    Show-HealthSummary
    
    if ($Continuous) {
        Write-Info "`nNext check in $IntervalMinutes minutes... (Ctrl+C to stop)"
        Start-Sleep -Seconds ($IntervalMinutes * 60)
        Clear-Host
    }
    
} while ($Continuous)

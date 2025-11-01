# ============================================================================
# TRAVIANT4.6 - XAMPP VALIDATION SCRIPT
# ============================================================================
# Version: 2.0
# Purpose: Comprehensive validation of XAMPP deployment
# Requirements: MySQL 8.0+, PostgreSQL 14+, PowerShell 5.1+, Apache 2.4+
# 
# USAGE:
#   .\scripts\validate-xampp.ps1
#   .\scripts\validate-xampp.ps1 -Verbose
#
# WHAT THIS SCRIPT VALIDATES:
# 1. Apache service status and port 80 availability
# 2. MySQL service status and connectivity
# 3. PostgreSQL service status and connectivity
# 4. All 8 game world databases exist
# 5. Each database has exactly 90 tables (T4.4 schema)
# 6. Test users exist (12 per database)
# 7. Connection config files exist and are valid
# 8. File permissions on critical directories
# 9. API endpoint accessibility (/v1/servers/loadServers)
# 10. Sample login query succeeds
# ============================================================================

param(
    [string]$MysqlHost = "localhost",
    [string]$MysqlUser = "travian",
    [string]$MysqlPassword = "TravianDB2025!",
    [string]$PgHost = "localhost",
    [string]$PgUser = "postgres",
    [string]$PgPassword = "postgres",
    [string]$PgDatabase = "travian_global",
    [switch]$Verbose
)

# Script Configuration
$ErrorActionPreference = "Continue"
$ScriptRoot = "C:\xampp\htdocs"
$SectionsDir = Join-Path $ScriptRoot "sections\servers"
$MysqlBin = "C:\xampp\mysql\bin\mysql.exe"
$PsqlBin = "C:\xampp\pgsql\bin\psql.exe"

# Expected Configuration
$ExpectedDatabases = @(
    "travian_world_speed10k",
    "travian_world_speed125k",
    "travian_world_speed250k",
    "travian_world_speed500k",
    "travian_world_speed5m",
    "travian_world_demo",
    "travian_world_dev",
    "travian_world_testworld"
)

$ExpectedWorlds = @("speed10k", "speed125k", "speed250k", "speed500k", "speed5m", "demo", "dev", "testworld")
$ExpectedTableCount = 90
$ExpectedTestUsers = @("testuser1", "testuser2", "testuser3", "testuser4", "testuser5", 
                       "testuser6", "testuser7", "testuser8", "testuser9", "testuser10",
                       "admin", "demo")

# Validation Results
$ValidationResults = @{
    ApacheRunning = $false
    MysqlRunning = $false
    PostgresRunning = $false
    MysqlConnection = $false
    PostgresConnection = $false
    Databases = 0
    Tables = @{}
    Users = @{}
    Configs = @{}
    Permissions = @{}
    ApiEndpoint = $false
    LoginTest = $false
    TotalChecks = 0
    PassedChecks = 0
}

# Color Output Functions
function Write-ColorOutput {
    param([string]$Message, [string]$Color = "White")
    Write-Host $Message -ForegroundColor $Color
}

function Write-Success { param([string]$Message) Write-ColorOutput "✓ $Message" "Green"; $script:ValidationResults.PassedChecks++ }
function Write-Fail { param([string]$Message) Write-ColorOutput "✗ $Message" "Red" }
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
# VALIDATION FUNCTIONS
# ============================================================================

function Test-ApacheService {
    Write-Info "Checking Apache service status..."
    $script:ValidationResults.TotalChecks++
    
    # Check if Apache process is running
    $apacheProcess = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
    
    if ($apacheProcess) {
        Write-Success "Apache is running (PID: $($apacheProcess.Id -join ', '))"
        $script:ValidationResults.ApacheRunning = $true
        
        # Test port 80
        try {
            $response = Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing -TimeoutSec 5
            if ($response.StatusCode -eq 200) {
                Write-Success "Apache responding on port 80"
                $script:ValidationResults.TotalChecks++
                $script:ValidationResults.PassedChecks++
            }
        } catch {
            Write-Fail "Apache not responding on port 80: $_"
        }
        
        return $true
    } else {
        Write-Fail "Apache is not running"
        return $false
    }
}

function Test-MySQLService {
    Write-Info "Checking MySQL service status..."
    $script:ValidationResults.TotalChecks++
    
    $mysqlProcess = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
    
    if ($mysqlProcess) {
        Write-Success "MySQL is running (PID: $($mysqlProcess.Id))"
        $script:ValidationResults.MysqlRunning = $true
        return $true
    } else {
        Write-Fail "MySQL is not running"
        return $false
    }
}

function Test-PostgreSQLService {
    Write-Info "Checking PostgreSQL service status..."
    $script:ValidationResults.TotalChecks++
    
    $pgProcess = Get-Process -Name "postgres" -ErrorAction SilentlyContinue
    
    if ($pgProcess) {
        Write-Success "PostgreSQL is running (PID: $($pgProcess.Id -join ', '))"
        $script:ValidationResults.PostgresRunning = $true
        return $true
    } else {
        Write-Fail "PostgreSQL is not running"
        return $false
    }
}

function Test-MySQLConnection {
    Write-Info "Testing MySQL connection..."
    $script:ValidationResults.TotalChecks++
    
    try {
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e "SELECT 1;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "MySQL connection successful (Host: $MysqlHost, User: $MysqlUser)"
            $script:ValidationResults.MysqlConnection = $true
            return $true
        } else {
            Write-Fail "MySQL connection failed: $result"
            return $false
        }
    } catch {
        Write-Fail "MySQL connection error: $_"
        return $false
    }
}

function Test-PostgreSQLConnection {
    Write-Info "Testing PostgreSQL connection..."
    $script:ValidationResults.TotalChecks++
    
    $env:PGPASSWORD = $PgPassword
    
    try {
        $result = & $PsqlBin -h $PgHost -U $PgUser -d $PgDatabase -c "SELECT 1;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "PostgreSQL connection successful (Host: $PgHost, Database: $PgDatabase)"
            $script:ValidationResults.PostgresConnection = $true
            return $true
        } else {
            Write-Fail "PostgreSQL connection failed: $result"
            return $false
        }
    } catch {
        Write-Fail "PostgreSQL connection error: $_"
        return $false
    } finally {
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
    }
}

function Test-Databases {
    Write-Info "Validating game world databases..."
    
    $found = 0
    
    foreach ($dbName in $ExpectedDatabases) {
        $script:ValidationResults.TotalChecks++
        
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$dbName';"
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query 2>&1
        
        if ($result -match $dbName) {
            Write-Success "Database exists: $dbName"
            $found++
        } else {
            Write-Fail "Database missing: $dbName"
        }
    }
    
    $script:ValidationResults.Databases = $found
    Write-Info "Found $found/$($ExpectedDatabases.Count) databases"
    
    return $found -eq $ExpectedDatabases.Count
}

function Test-DatabaseTables {
    Write-Info "Validating database tables..."
    
    $allValid = $true
    
    foreach ($dbName in $ExpectedDatabases) {
        $script:ValidationResults.TotalChecks++
        
        $query = "SELECT COUNT(*) as table_count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$dbName' AND TABLE_TYPE='BASE TABLE';"
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query -s -N 2>&1
        
        $tableCount = [int]$result
        $script:ValidationResults.Tables[$dbName] = $tableCount
        
        if ($tableCount -eq $ExpectedTableCount) {
            Write-Success "Database $dbName has $tableCount tables (expected: $ExpectedTableCount)"
        } else {
            Write-Fail "Database $dbName has $tableCount tables (expected: $ExpectedTableCount)"
            $allValid = $false
        }
    }
    
    return $allValid
}

function Test-TestUsers {
    Write-Info "Validating test user accounts..."
    
    $allValid = $true
    
    foreach ($dbName in $ExpectedDatabases) {
        $script:ValidationResults.TotalChecks++
        
        $query = "SELECT COUNT(*) as user_count FROM $dbName.users WHERE name IN ('" + ($ExpectedTestUsers -join "','") + "');"
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query -s -N 2>&1
        
        $userCount = [int]$result
        $script:ValidationResults.Users[$dbName] = $userCount
        
        if ($userCount -eq $ExpectedTestUsers.Count) {
            Write-Success "Database $dbName has $userCount/$($ExpectedTestUsers.Count) test users"
        } else {
            Write-Fail "Database $dbName has $userCount/$($ExpectedTestUsers.Count) test users"
            $allValid = $false
        }
        
        if ($Verbose -and $userCount -gt 0) {
            $userQuery = "SELECT name, email, race, access FROM $dbName.users WHERE name IN ('" + ($ExpectedTestUsers -join "','") + "');"
            $users = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $userQuery 2>&1
            Write-Host $users
        }
    }
    
    return $allValid
}

function Test-ConnectionConfigs {
    Write-Info "Validating connection configuration files..."
    
    $allValid = $true
    
    foreach ($worldId in $ExpectedWorlds) {
        $script:ValidationResults.TotalChecks++
        
        $configPath = Join-Path $SectionsDir "$worldId\config\connection.php"
        
        if (Test-Path $configPath) {
            Write-Success "Config exists: $worldId"
            $script:ValidationResults.Configs[$worldId] = $true
            
            if ($Verbose) {
                $content = Get-Content $configPath -Raw
                if ($content -match '{{.*}}') {
                    Write-Warning "Config for $worldId still contains placeholders"
                    $allValid = $false
                }
            }
        } else {
            Write-Fail "Config missing: $worldId (Expected: $configPath)"
            $script:ValidationResults.Configs[$worldId] = $false
            $allValid = $false
        }
    }
    
    return $allValid
}

function Test-FilePermissions {
    Write-Info "Checking file permissions..."
    
    $criticalDirs = @(
        "C:\xampp\htdocs",
        "C:\xampp\tmp",
        "C:\xampp\apache\logs"
    )
    
    $allValid = $true
    
    foreach ($dir in $criticalDirs) {
        $script:ValidationResults.TotalChecks++
        
        if (Test-Path $dir) {
            try {
                # Test write access
                $testFile = Join-Path $dir "test-write-$(Get-Random).tmp"
                "test" | Out-File -FilePath $testFile -ErrorAction Stop
                Remove-Item $testFile -Force
                
                Write-Success "Directory writable: $dir"
                $script:ValidationResults.Permissions[$dir] = $true
            } catch {
                Write-Fail "Directory not writable: $dir"
                $script:ValidationResults.Permissions[$dir] = $false
                $allValid = $false
            }
        } else {
            Write-Fail "Directory missing: $dir"
            $allValid = $false
        }
    }
    
    return $allValid
}

function Test-ApiEndpoint {
    Write-Info "Testing API endpoint..."
    $script:ValidationResults.TotalChecks++
    
    try {
        $response = Invoke-WebRequest -Uri "http://localhost/v1/servers/loadServers" -UseBasicParsing -TimeoutSec 10
        
        if ($response.StatusCode -eq 200) {
            $json = $response.Content | ConvertFrom-Json
            $serverCount = ($json.data | Measure-Object).Count
            
            Write-Success "API endpoint accessible - $serverCount servers returned"
            $script:ValidationResults.ApiEndpoint = $true
            
            if ($Verbose) {
                Write-Host ($response.Content | ConvertFrom-Json | ConvertTo-Json -Depth 5)
            }
            
            return $true
        } else {
            Write-Fail "API returned status code: $($response.StatusCode)"
            return $false
        }
    } catch {
        Write-Fail "API endpoint test failed: $_"
        return $false
    }
}

function Test-SampleLoginQuery {
    Write-Info "Testing sample login query..."
    $script:ValidationResults.TotalChecks++
    
    $dbName = $ExpectedDatabases[0]
    $testUser = "testuser1"
    
    $query = "SELECT id, name, email, password FROM $dbName.users WHERE name='$testUser' LIMIT 1;"
    
    try {
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query 2>&1
        
        if ($LASTEXITCODE -eq 0 -and $result -match $testUser) {
            Write-Success "Sample login query successful for user: $testUser"
            $script:ValidationResults.LoginTest = $true
            
            if ($Verbose) {
                Write-Host $result
            }
            
            return $true
        } else {
            Write-Fail "Sample login query failed for user: $testUser"
            return $false
        }
    } catch {
        Write-Fail "Sample login query error: $_"
        return $false
    }
}

function Show-ValidationSummary {
    Write-Header "VALIDATION SUMMARY"
    
    $passRate = if ($ValidationResults.TotalChecks -gt 0) {
        [math]::Round(($ValidationResults.PassedChecks / $ValidationResults.TotalChecks) * 100, 2)
    } else { 0 }
    
    Write-Info "Total Checks: $($ValidationResults.TotalChecks)"
    Write-ColorOutput "Passed: $($ValidationResults.PassedChecks)" "Green"
    Write-ColorOutput "Failed: $($ValidationResults.TotalChecks - $ValidationResults.PassedChecks)" "Red"
    Write-Info "Pass Rate: $passRate%"
    Write-Host ""
    
    Write-Info "Detailed Results:"
    Write-Info "  Services:"
    Write-Info "    Apache Running: $(if ($ValidationResults.ApacheRunning) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info "    MySQL Running: $(if ($ValidationResults.MysqlRunning) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info "    PostgreSQL Running: $(if ($ValidationResults.PostgresRunning) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info ""
    Write-Info "  Database Connections:"
    Write-Info "    MySQL Connection: $(if ($ValidationResults.MysqlConnection) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info "    PostgreSQL Connection: $(if ($ValidationResults.PostgresConnection) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info ""
    Write-Info "  Databases: $($ValidationResults.Databases)/$($ExpectedDatabases.Count)"
    
    Write-Info "  Tables per Database:"
    foreach ($db in $ValidationResults.Tables.Keys) {
        $status = if ($ValidationResults.Tables[$db] -eq $ExpectedTableCount) { "✓" } else { "✗" }
        Write-Info "    $status $db : $($ValidationResults.Tables[$db])/$ExpectedTableCount"
    }
    
    Write-Info "  Users per Database:"
    foreach ($db in $ValidationResults.Users.Keys) {
        $status = if ($ValidationResults.Users[$db] -eq $ExpectedTestUsers.Count) { "✓" } else { "✗" }
        Write-Info "    $status $db : $($ValidationResults.Users[$db])/$($ExpectedTestUsers.Count)"
    }
    
    Write-Info "  Connection Configs:"
    foreach ($world in $ValidationResults.Configs.Keys) {
        $status = if ($ValidationResults.Configs[$world]) { "✓" } else { "✗" }
        Write-Info "    $status $world"
    }
    
    Write-Info "  API Endpoint: $(if ($ValidationResults.ApiEndpoint) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info "  Sample Login Test: $(if ($ValidationResults.LoginTest) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info ""
    
    if ($passRate -eq 100) {
        Write-ColorOutput "🎉 ALL VALIDATION CHECKS PASSED! 🎉" "Green"
        Write-Host ""
        Write-Info "Your XAMPP deployment is ready for Travian T4.6!"
        Write-Info ""
        Write-Info "Next Steps:"
        Write-Info "1. Open browser: http://localhost"
        Write-Info "2. Select a speed server"
        Write-Info "3. Login with testuser1 / test123"
        return $true
    } else {
        Write-ColorOutput "⚠ VALIDATION FAILED ⚠" "Red"
        Write-Host ""
        Write-Info "Please review the failed checks above and run setup again:"
        Write-Info "  .\scripts\setup-xampp.ps1 -Force"
        return $false
    }
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

Write-Header "TRAVIANT4.6 - XAMPP VALIDATION"
Write-Info "MySQL Host: $MysqlHost"
Write-Info "MySQL User: $MysqlUser"
Write-Info "PostgreSQL Host: $PgHost"
Write-Info "PostgreSQL Database: $PgDatabase"
Write-Info "Expected Databases: $($ExpectedDatabases.Count)"
Write-Info "Expected Tables per DB: $ExpectedTableCount"
Write-Info "Expected Test Users: $($ExpectedTestUsers.Count)"

# Run all validation checks
Write-Header "SERVICE CHECKS"
$apacheOk = Test-ApacheService
$mysqlServiceOk = Test-MySQLService
$pgServiceOk = Test-PostgreSQLService

Write-Header "CONNECTION CHECKS"
$mysqlConnOk = Test-MySQLConnection
$pgConnOk = Test-PostgreSQLConnection

if ($mysqlConnOk) {
    Write-Header "DATABASE CHECKS"
    $databasesOk = Test-Databases
    $tablesOk = Test-DatabaseTables
    $usersOk = Test-TestUsers
    $configsOk = Test-ConnectionConfigs
}

Write-Header "FILE SYSTEM CHECKS"
$permissionsOk = Test-FilePermissions

if ($apacheOk) {
    Write-Header "API CHECKS"
    $apiOk = Test-ApiEndpoint
}

if ($mysqlConnOk) {
    Write-Header "LOGIN CHECKS"
    $loginTestOk = Test-SampleLoginQuery
}

# Show summary
$allPassed = Show-ValidationSummary

# Exit with appropriate code
exit $(if ($allPassed) { 0 } else { 1 })

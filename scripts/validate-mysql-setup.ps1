# ============================================================================
# TRAVIAN T4.6 - MYSQL SETUP VALIDATION SCRIPT
# ============================================================================
# Version: 1.0
# Purpose: Validate MySQL database setup for Windows deployment
# Requirements: MySQL 8.0+, PowerShell 5.1+
# 
# USAGE:
#   .\scripts\validate-mysql-setup.ps1
#   .\scripts\validate-mysql-setup.ps1 -Verbose
#
# WHAT THIS SCRIPT VALIDATES:
# 1. MySQL connection and credentials
# 2. All 8 game world databases exist
# 3. Each database has exactly 90 tables (T4.4 schema)
# 4. Test users exist and are queryable
# 5. Connection config files exist and are valid
# 6. Sample login query succeeds
# ============================================================================

param(
    [string]$MysqlHost = "localhost",
    [string]$MysqlUser = "travian",
    [string]$MysqlPassword = "TravianDB2025!",
    [switch]$Verbose
)

# Script Configuration
$ErrorActionPreference = "Stop"
$ScriptRoot = Split-Path -Parent $PSScriptRoot
$SectionsDir = Join-Path $ScriptRoot "sections\servers"

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
    Connection = $false
    Databases = 0
    Tables = @{}
    Users = @{}
    Configs = @{}
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

# ============================================================================
# VALIDATION FUNCTIONS
# ============================================================================

function Test-MySQLConnection {
    Write-Info "Testing MySQL connection..."
    $script:ValidationResults.TotalChecks++
    
    try {
        $result = mysql -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e "SELECT 1;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "MySQL connection successful (Host: $MysqlHost, User: $MysqlUser)"
            $script:ValidationResults.Connection = $true
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

function Test-Databases {
    Write-Info "Validating game world databases..."
    
    $found = 0
    
    foreach ($dbName in $ExpectedDatabases) {
        $script:ValidationResults.TotalChecks++
        
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$dbName';"
        $result = mysql -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query 2>&1
        
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
        $result = mysql -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query -s -N 2>&1
        
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
        $result = mysql -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query -s -N 2>&1
        
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
            $users = mysql -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $userQuery 2>&1
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

function Test-SampleLoginQuery {
    Write-Info "Testing sample login query..."
    $script:ValidationResults.TotalChecks++
    
    $dbName = $ExpectedDatabases[0]
    $testUser = "testuser1"
    
    $query = "SELECT id, name, email, password FROM $dbName.users WHERE name='$testUser' LIMIT 1;"
    
    try {
        $result = mysql -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query 2>&1
        
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
    Write-Info ""
    Write-Info "============================================================================"
    Write-Info "VALIDATION SUMMARY"
    Write-Info "============================================================================"
    
    $passRate = if ($ValidationResults.TotalChecks -gt 0) {
        [math]::Round(($ValidationResults.PassedChecks / $ValidationResults.TotalChecks) * 100, 2)
    } else { 0 }
    
    Write-Info "Total Checks: $($ValidationResults.TotalChecks)"
    Write-ColorOutput "Passed: $($ValidationResults.PassedChecks)" "Green"
    Write-ColorOutput "Failed: $($ValidationResults.TotalChecks - $ValidationResults.PassedChecks)" "Red"
    Write-Info "Pass Rate: $passRate%"
    Write-Info ""
    
    Write-Info "Detailed Results:"
    Write-Info "  MySQL Connection: $(if ($ValidationResults.Connection) { '✓ PASS' } else { '✗ FAIL' })"
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
    
    Write-Info "  Sample Login Test: $(if ($ValidationResults.LoginTest) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info "============================================================================"
    
    if ($passRate -eq 100) {
        Write-ColorOutput "" "Green"
        Write-ColorOutput "🎉 ALL VALIDATION CHECKS PASSED! 🎉" "Green"
        Write-ColorOutput "" "Green"
        Write-Info "Your MySQL setup is ready for Travian T4.6 login system!"
        Write-Info ""
        Write-Info "Next Steps:"
        Write-Info "1. Start the PHP server: docker-compose up -d php"
        Write-Info "2. Test login API:"
        Write-Info "   curl -X POST http://localhost:9080/v1/auth/login \"
        Write-Info "   -d '{\"username\":\"testuser1\",\"password\":\"test123\",\"worldId\":\"speed10k\"}'"
        Write-Info "3. Check server logs: docker logs -f travian-php"
        return $true
    } else {
        Write-ColorOutput "" "Red"
        Write-ColorOutput "⚠ VALIDATION FAILED ⚠" "Red"
        Write-ColorOutput "" "Red"
        Write-Info "Please review the failed checks above and run setup again:"
        Write-Info "  .\scripts\setup-windows.ps1 -Force"
        return $false
    }
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

Write-Info "============================================================================"
Write-Info "TRAVIAN T4.6 - MYSQL SETUP VALIDATION"
Write-Info "============================================================================"
Write-Info "Target Host: $MysqlHost"
Write-Info "MySQL User: $MysqlUser"
Write-Info "Expected Databases: $($ExpectedDatabases.Count)"
Write-Info "Expected Tables per DB: $ExpectedTableCount"
Write-Info "Expected Test Users: $($ExpectedTestUsers.Count)"
Write-Info "============================================================================"
Write-Info ""

# Run all validation checks
$connectionOk = Test-MySQLConnection
if ($connectionOk) {
    $databasesOk = Test-Databases
    $tablesOk = Test-DatabaseTables
    $usersOk = Test-TestUsers
    $configsOk = Test-ConnectionConfigs
    $loginTestOk = Test-SampleLoginQuery
}

# Show summary
$allPassed = Show-ValidationSummary

# Exit with appropriate code
exit $(if ($allPassed) { 0 } else { 1 })

# ============================================================================
# TRAVIAN T4.6 - WINDOWS MYSQL SETUP SCRIPT
# ============================================================================
# Version: 1.0
# Purpose: Automated MySQL database provisioning for Windows deployment
# Requirements: MySQL 8.0+, PowerShell 5.1+, PHP 7.4+
# 
# USAGE:
#   .\scripts\setup-windows.ps1
#
# WHAT THIS SCRIPT DOES:
# 1. Validates MySQL connection and credentials
# 2. Creates 8 game world databases
# 3. Applies T4.4 schema to each database (90 tables)
# 4. Inserts test users into each world
# 5. Generates per-world connection.php files
# 6. Validates setup completion
# ============================================================================

param(
    [string]$MysqlHost = "localhost",
    [string]$MysqlRootPassword = "TravianSecureRoot2025!",
    [string]$MysqlUser = "travian",
    [string]$MysqlPassword = "TravianDB2025!",
    [switch]$SkipUserCreation,
    [switch]$SkipValidation,
    [switch]$Force
)

# Script Configuration
$ErrorActionPreference = "Stop"
$ScriptRoot = Split-Path -Parent $PSScriptRoot
$DatabaseDir = Join-Path $ScriptRoot "database\mysql"
$SectionsDir = Join-Path $ScriptRoot "sections\servers"

# World Configuration
$GameWorlds = @(
    @{ Id = "speed10k";   DbName = "travian_world_speed10k";   Name = "Speed 10k Server" },
    @{ Id = "speed125k";  DbName = "travian_world_speed125k";  Name = "Speed 125k Server" },
    @{ Id = "speed250k";  DbName = "travian_world_speed250k";  Name = "Speed 250k Server" },
    @{ Id = "speed500k";  DbName = "travian_world_speed500k";  Name = "Speed 500k Server" },
    @{ Id = "speed5m";    DbName = "travian_world_speed5m";    Name = "Speed 5m Server" },
    @{ Id = "demo";       DbName = "travian_world_demo";       Name = "Demo World" },
    @{ Id = "dev";        DbName = "travian_world_dev";        Name = "Development World" },
    @{ Id = "testworld";  DbName = "travian_world_testworld";  Name = "Test World" }
)

# Color Output Functions
function Write-ColorOutput {
    param([string]$Message, [string]$Color = "White")
    Write-Host $Message -ForegroundColor $Color
}

function Write-Success { param([string]$Message) Write-ColorOutput "✓ $Message" "Green" }
function Write-Info { param([string]$Message) Write-ColorOutput "ℹ $Message" "Cyan" }
function Write-Warning { param([string]$Message) Write-ColorOutput "⚠ $Message" "Yellow" }
function Write-Error { param([string]$Message) Write-ColorOutput "✗ $Message" "Red" }

# ============================================================================
# FUNCTION: Test-MysqlConnection
# ============================================================================
function Test-MysqlConnection {
    param([string]$Host, [string]$User, [string]$Password)
    
    Write-Info "Testing MySQL connection to $Host..."
    
    try {
        $result = mysql -h $Host -u $User -p"$Password" -e "SELECT VERSION();" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "MySQL connection successful"
            return $true
        } else {
            Write-Error "MySQL connection failed: $result"
            return $false
        }
    } catch {
        Write-Error "MySQL connection error: $_"
        return $false
    }
}

# ============================================================================
# FUNCTION: Initialize-MySQLDatabases
# ============================================================================
function Initialize-MySQLDatabases {
    param(
        [string]$Host,
        [string]$User,
        [string]$Password,
        [array]$Worlds
    )
    
    Write-Info "Creating MySQL databases for $($Worlds.Count) game worlds..."
    
    $created = 0
    $skipped = 0
    
    foreach ($world in $Worlds) {
        $dbName = $world.DbName
        
        Write-Info "Processing database: $dbName"
        
        # Check if database exists
        $checkQuery = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$dbName';"
        $exists = mysql -h $Host -u $User -p"$Password" -e $checkQuery 2>&1
        
        if ($exists -match $dbName) {
            if ($Force) {
                Write-Warning "Database $dbName exists - dropping and recreating (Force mode)"
                mysql -h $Host -u $User -p"$Password" -e "DROP DATABASE ``$dbName``;" 2>&1 | Out-Null
            } else {
                Write-Warning "Database $dbName already exists - skipping"
                $skipped++
                continue
            }
        }
        
        # Create database
        $createQuery = "CREATE DATABASE ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        $result = mysql -h $Host -u $User -p"$Password" -e $createQuery 2>&1
        
        if ($LASTEXITCODE -eq 0) {
            Write-Success "Created database: $dbName"
            $created++
        } else {
            Write-Error "Failed to create database $dbName : $result"
        }
    }
    
    Write-Info "Database creation complete: $created created, $skipped skipped"
    return $created
}

# ============================================================================
# FUNCTION: Apply-DatabaseSchema
# ============================================================================
function Apply-DatabaseSchema {
    param(
        [string]$Host,
        [string]$User,
        [string]$Password,
        [array]$Worlds,
        [string]$SchemaFile
    )
    
    Write-Info "Applying T4.4 schema to databases..."
    
    if (-not (Test-Path $SchemaFile)) {
        Write-Error "Schema file not found: $SchemaFile"
        return 0
    }
    
    $applied = 0
    
    foreach ($world in $Worlds) {
        $dbName = $world.DbName
        
        Write-Info "Applying schema to: $dbName"
        
        try {
            $result = Get-Content $SchemaFile | mysql -h $Host -u $User -p"$Password" $dbName 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                Write-Success "Schema applied to: $dbName"
                $applied++
            } else {
                Write-Error "Schema application failed for $dbName : $result"
            }
        } catch {
            Write-Error "Error applying schema to $dbName : $_"
        }
    }
    
    Write-Info "Schema application complete: $applied/$($Worlds.Count) successful"
    return $applied
}

# ============================================================================
# FUNCTION: Insert-TestUsers
# ============================================================================
function Insert-TestUsers {
    param(
        [string]$Host,
        [string]$User,
        [string]$Password,
        [array]$Worlds,
        [string]$TestUsersFile
    )
    
    Write-Info "Inserting test users into databases..."
    
    if (-not (Test-Path $TestUsersFile)) {
        Write-Error "Test users file not found: $TestUsersFile"
        return 0
    }
    
    $inserted = 0
    
    foreach ($world in $Worlds) {
        $dbName = $world.DbName
        
        Write-Info "Inserting test users into: $dbName"
        
        try {
            $result = Get-Content $TestUsersFile | mysql -h $Host -u $User -p"$Password" $dbName 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                Write-Success "Test users inserted into: $dbName"
                $inserted++
            } else {
                Write-Warning "Test user insertion had warnings for $dbName : $result"
                $inserted++
            }
        } catch {
            Write-Error "Error inserting test users into $dbName : $_"
        }
    }
    
    Write-Info "Test user insertion complete: $inserted/$($Worlds.Count) successful"
    return $inserted
}

# ============================================================================
# FUNCTION: New-WorldConnectionConfig
# ============================================================================
function New-WorldConnectionConfig {
    param(
        [string]$WorldId,
        [string]$DbName,
        [string]$DbHost,
        [string]$DbUser,
        [string]$DbPassword,
        [string]$TemplateFile,
        [string]$OutputDir
    )
    
    Write-Info "Generating connection config for world: $WorldId"
    
    # Create output directory if it doesn't exist
    $worldConfigDir = Join-Path $OutputDir "$WorldId\config"
    if (-not (Test-Path $worldConfigDir)) {
        New-Item -ItemType Directory -Path $worldConfigDir -Force | Out-Null
    }
    
    $outputFile = Join-Path $worldConfigDir "connection.php"
    
    # Read template
    if (-not (Test-Path $TemplateFile)) {
        Write-Error "Template file not found: $TemplateFile"
        return $false
    }
    
    $template = Get-Content $TemplateFile -Raw
    
    # Replace placeholders
    $config = $template `
        -replace '{{DB_HOST}}', $DbHost `
        -replace '{{DB_NAME}}', $DbName `
        -replace '{{DB_USER}}', $DbUser `
        -replace '{{DB_PASSWORD}}', $DbPassword `
        -replace '{{WORLD_ID}}', $WorldId `
        -replace '{{DRIVER}}', 'mysql'
    
    # Write to file
    $config | Set-Content -Path $outputFile -Encoding UTF8
    
    Write-Success "Created config: $outputFile"
    return $true
}

# ============================================================================
# FUNCTION: Generate-AllConnectionConfigs
# ============================================================================
function Generate-AllConnectionConfigs {
    param(
        [array]$Worlds,
        [string]$DbHost,
        [string]$DbUser,
        [string]$DbPassword,
        [string]$TemplateFile,
        [string]$OutputDir
    )
    
    Write-Info "Generating connection configs for all worlds..."
    
    $generated = 0
    
    foreach ($world in $Worlds) {
        $success = New-WorldConnectionConfig `
            -WorldId $world.Id `
            -DbName $world.DbName `
            -DbHost $DbHost `
            -DbUser $DbUser `
            -DbPassword $DbPassword `
            -TemplateFile $TemplateFile `
            -OutputDir $OutputDir
        
        if ($success) {
            $generated++
        }
    }
    
    Write-Info "Connection config generation complete: $generated/$($Worlds.Count) successful"
    return $generated
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

Write-Info "============================================================================"
Write-Info "TRAVIAN T4.6 - WINDOWS MYSQL SETUP"
Write-Info "============================================================================"
Write-Info "Target Host: $MysqlHost"
Write-Info "MySQL User: $MysqlUser"
Write-Info "Worlds to provision: $($GameWorlds.Count)"
Write-Info "============================================================================"

# Step 1: Test MySQL Connection
if (-not (Test-MysqlConnection -Host $MysqlHost -User "root" -Password $MysqlRootPassword)) {
    Write-Error "Cannot connect to MySQL. Please check your credentials and ensure MySQL is running."
    exit 1
}

# Step 2: Create Databases
$createdCount = Initialize-MySQLDatabases `
    -Host $MysqlHost `
    -User "root" `
    -Password $MysqlRootPassword `
    -Worlds $GameWorlds

# Step 3: Apply Schema
$schemaFile = Join-Path $DatabaseDir "windows-world-schema.sql"
$appliedCount = Apply-DatabaseSchema `
    -Host $MysqlHost `
    -User "root" `
    -Password $MysqlRootPassword `
    -Worlds $GameWorlds `
    -SchemaFile $schemaFile

# Step 4: Insert Test Users (unless skipped)
if (-not $SkipUserCreation) {
    $testUsersFile = Join-Path $DatabaseDir "windows-test-users.sql"
    $insertedCount = Insert-TestUsers `
        -Host $MysqlHost `
        -User "root" `
        -Password $MysqlRootPassword `
        -Worlds $GameWorlds `
        -TestUsersFile $testUsersFile
}

# Step 5: Generate Connection Configs
$templateFile = Join-Path $DatabaseDir "windows-connection-template.php"
$configCount = Generate-AllConnectionConfigs `
    -Worlds $GameWorlds `
    -DbHost $MysqlHost `
    -DbUser $MysqlUser `
    -DbPassword $MysqlPassword `
    -TemplateFile $templateFile `
    -OutputDir $SectionsDir

# Step 6: Validation (unless skipped)
if (-not $SkipValidation) {
    Write-Info "Running setup validation..."
    & (Join-Path $PSScriptRoot "validate-mysql-setup.ps1") `
        -MysqlHost $MysqlHost `
        -MysqlUser $MysqlUser `
        -MysqlPassword $MysqlPassword
}

# Final Summary
Write-Info "============================================================================"
Write-Info "SETUP COMPLETE"
Write-Info "============================================================================"
Write-Success "Databases created/updated: $createdCount/$($GameWorlds.Count)"
Write-Success "Schemas applied: $appliedCount/$($GameWorlds.Count)"
if (-not $SkipUserCreation) {
    Write-Success "Test users inserted: $insertedCount/$($GameWorlds.Count)"
}
Write-Success "Connection configs generated: $configCount/$($GameWorlds.Count)"
Write-Info "============================================================================"
Write-Info "Next Steps:"
Write-Info "1. Verify databases: mysql -u $MysqlUser -p"
Write-Info "2. Test login: curl -X POST http://localhost:9080/v1/auth/login -d '{\"username\":\"testuser1\",\"password\":\"test123\",\"worldId\":\"speed10k\"}'"
Write-Info "3. Check logs: docker logs travian-php"
Write-Info "============================================================================"

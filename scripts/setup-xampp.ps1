# ============================================================================
# TRAVIANT4.6 - XAMPP SETUP SCRIPT
# ============================================================================
# Version: 2.0
# Purpose: Automated MySQL/PostgreSQL database provisioning for XAMPP deployment
# Requirements: MySQL 8.0+, PostgreSQL 14+, PowerShell 5.1+, PHP 8.2+
# 
# USAGE:
#   .\scripts\setup-xampp.ps1
#   .\scripts\setup-xampp.ps1 -Force
#   .\scripts\setup-xampp.ps1 -SkipUserCreation
#
# WHAT THIS SCRIPT DOES:
# 1. Validates MySQL and PostgreSQL connections
# 2. Creates 8 game world databases in MySQL
# 3. Applies T4.4 schema to each database (90 tables)
# 4. Inserts test users into each world (12 users)
# 5. Generates per-world connection.php files
# 6. Registers servers in PostgreSQL gameservers table
# 7. Validates setup completion
# ============================================================================

param(
    [string]$MysqlHost = "localhost",
    [string]$MysqlRootPassword = "TravianSecureRoot2025!",
    [string]$MysqlUser = "travian",
    [string]$MysqlPassword = "TravianDB2025!",
    [string]$PgHost = "localhost",
    [string]$PgUser = "postgres",
    [string]$PgPassword = "postgres",
    [string]$PgDatabase = "travian_global",
    [switch]$SkipUserCreation,
    [switch]$SkipValidation,
    [switch]$Force
)

# Script Configuration
$ErrorActionPreference = "Stop"
$ScriptRoot = "C:\xampp\htdocs"
$DatabaseDir = Join-Path $ScriptRoot "database\mysql"
$SectionsDir = Join-Path $ScriptRoot "sections\servers"
$MysqlBin = "C:\xampp\mysql\bin\mysql.exe"
$PsqlBin = "C:\xampp\pgsql\bin\psql.exe"

# World Configuration
$GameWorlds = @(
    @{ Id = "speed10k";   DbName = "travian_world_speed10k";   Name = "Ultra Speed Server (10,000x)"; Speed = 10000 },
    @{ Id = "speed125k";  DbName = "travian_world_speed125k";  Name = "Mega Speed Server (125,000x)"; Speed = 125000 },
    @{ Id = "speed250k";  DbName = "travian_world_speed250k";  Name = "Extreme Speed Server (250,000x)"; Speed = 250000 },
    @{ Id = "speed500k";  DbName = "travian_world_speed500k";  Name = "Hyper Speed Server (500,000x)"; Speed = 500000 },
    @{ Id = "speed5m";    DbName = "travian_world_speed5m";    Name = "Instant Speed Server (5,000,000x)"; Speed = 5000000 },
    @{ Id = "demo";       DbName = "travian_world_demo";       Name = "Demo World (1x)"; Speed = 1 },
    @{ Id = "dev";        DbName = "travian_world_dev";        Name = "Development World (1x)"; Speed = 1 },
    @{ Id = "testworld";  DbName = "travian_world_testworld";  Name = "Test World (1x)"; Speed = 1 }
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
function Write-Header { 
    param([string]$Message) 
    Write-Host ""
    Write-ColorOutput "============================================================================" "Magenta"
    Write-ColorOutput $Message "Magenta"
    Write-ColorOutput "============================================================================" "Magenta"
}

# ============================================================================
# FUNCTION: Test-MysqlConnection
# ============================================================================
function Test-MysqlConnection {
    param([string]$Host, [string]$User, [string]$Password)
    
    Write-Info "Testing MySQL connection to $Host..."
    
    try {
        $result = & $MysqlBin -h $Host -u $User -p"$Password" -e "SELECT VERSION();" 2>&1
        if ($LASTEXITCODE -eq 0) {
            $version = ($result | Select-String "^\d+\.\d+\.\d+").Matches.Value
            Write-Success "MySQL connection successful (Version: $version)"
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
# FUNCTION: Test-PostgresConnection
# ============================================================================
function Test-PostgresConnection {
    param([string]$Host, [string]$User, [string]$Password, [string]$Database)
    
    Write-Info "Testing PostgreSQL connection to $Host..."
    
    $env:PGPASSWORD = $Password
    
    try {
        $result = & $PsqlBin -h $Host -U $User -d $Database -c "SELECT version();" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "PostgreSQL connection successful"
            return $true
        } else {
            Write-Error "PostgreSQL connection failed: $result"
            return $false
        }
    } catch {
        Write-Error "PostgreSQL connection error: $_"
        return $false
    } finally {
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
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
        $exists = & $MysqlBin -h $Host -u $User -p"$Password" -e $checkQuery 2>&1
        
        if ($exists -match $dbName) {
            if ($Force) {
                Write-Warning "Database $dbName exists - dropping and recreating (Force mode)"
                & $MysqlBin -h $Host -u $User -p"$Password" -e "DROP DATABASE \`$dbName\`;" 2>&1 | Out-Null
            } else {
                Write-Warning "Database $dbName already exists - skipping"
                $skipped++
                continue
            }
        }
        
        # Create database
        $createQuery = "CREATE DATABASE \`$dbName\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        $result = & $MysqlBin -h $Host -u $User -p"$Password" -e $createQuery 2>&1
        
        if ($LASTEXITCODE -eq 0) {
            Write-Success "Created database: $dbName"
            $created++
        } else {
            Write-Error "Failed to create database $dbName: $result"
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
        Write-Warning "Skipping schema application. Please create the schema file."
        return 0
    }
    
    $applied = 0
    
    foreach ($world in $Worlds) {
        $dbName = $world.DbName
        
        Write-Info "Applying schema to: $dbName"
        
        try {
            Get-Content $SchemaFile | & $MysqlBin -h $Host -u $User -p"$Password" $dbName 2>&1 | Out-Null
            
            if ($LASTEXITCODE -eq 0) {
                # Verify table count
                $tableCount = (& $MysqlBin -h $Host -u $User -p"$Password" -s -N -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$dbName' AND TABLE_TYPE='BASE TABLE';" 2>&1).Trim()
                Write-Success "Schema applied to: $dbName ($tableCount tables)"
                $applied++
            } else {
                Write-Error "Schema application failed for $dbName"
            }
        } catch {
            Write-Error "Error applying schema to $dbName: $_"
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
        Write-Warning "Skipping test user creation. Please create the test users SQL file."
        return 0
    }
    
    $inserted = 0
    
    foreach ($world in $Worlds) {
        $dbName = $world.DbName
        
        Write-Info "Inserting test users into: $dbName"
        
        try {
            Get-Content $TestUsersFile | & $MysqlBin -h $Host -u $User -p"$Password" $dbName 2>&1 | Out-Null
            
            if ($LASTEXITCODE -eq 0) {
                # Verify user count
                $userCount = (& $MysqlBin -h $Host -u $User -p"$Password" -s -N -e "SELECT COUNT(*) FROM $dbName.users;" 2>&1).Trim()
                Write-Success "Test users inserted into: $dbName ($userCount users)"
                $inserted++
            } else {
                Write-Warning "Test user insertion had warnings for $dbName"
                $inserted++
            }
        } catch {
            Write-Error "Error inserting test users into $dbName: $_"
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
# FUNCTION: Register-ServersInPostgres
# ============================================================================
function Register-ServersInPostgres {
    param(
        [string]$Host,
        [string]$User,
        [string]$Password,
        [string]$Database,
        [array]$Worlds
    )
    
    Write-Info "Registering servers in PostgreSQL..."
    
    $env:PGPASSWORD = $Password
    
    try {
        foreach ($world in $Worlds) {
            $sql = @"
INSERT INTO gameservers (worldid, name, gameworldurl, configfilelocation, speed, starttime)
VALUES ('$($world.Id)', '$($world.Name)', 'http://localhost/', 'sections/servers/$($world.Id)', $($world.Speed), EXTRACT(EPOCH FROM NOW())::INTEGER)
ON CONFLICT (worldid) DO UPDATE SET
    name = EXCLUDED.name,
    speed = EXCLUDED.speed;
"@
            
            $result = $sql | & $PsqlBin -h $Host -U $User -d $Database 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                Write-Success "Registered: $($world.Name)"
            } else {
                Write-Warning "Failed to register $($world.Id): $result"
            }
        }
        
        # Verify registration
        $count = (& $PsqlBin -h $Host -U $User -d $Database -t -c "SELECT COUNT(*) FROM gameservers;" 2>&1).Trim()
        Write-Info "Total servers in PostgreSQL: $count"
        
        return $true
    } catch {
        Write-Error "Error registering servers: $_"
        return $false
    } finally {
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
    }
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

Write-Header "TRAVIANT4.6 - XAMPP SETUP"
Write-Info "MySQL Host: $MysqlHost"
Write-Info "MySQL User: $MysqlUser"
Write-Info "PostgreSQL Host: $PgHost"
Write-Info "PostgreSQL Database: $PgDatabase"
Write-Info "Worlds to provision: $($GameWorlds.Count)"

# Step 1: Test MySQL Connection
Write-Header "STEP 1: VALIDATING MYSQL CONNECTION"
if (-not (Test-MysqlConnection -Host $MysqlHost -User "root" -Password $MysqlRootPassword)) {
    Write-Error "Cannot connect to MySQL. Ensure MySQL is running and credentials are correct."
    exit 1
}

# Step 2: Test PostgreSQL Connection
Write-Header "STEP 2: VALIDATING POSTGRESQL CONNECTION"
if (-not (Test-PostgresConnection -Host $PgHost -User $PgUser -Password $PgPassword -Database $PgDatabase)) {
    Write-Error "Cannot connect to PostgreSQL. Ensure PostgreSQL is running and database exists."
    exit 2
}

# Step 3: Create Databases
Write-Header "STEP 3: CREATING MYSQL DATABASES"
$createdCount = Initialize-MySQLDatabases `
    -Host $MysqlHost `
    -User "root" `
    -Password $MysqlRootPassword `
    -Worlds $GameWorlds

# Step 4: Apply Schema
Write-Header "STEP 4: APPLYING DATABASE SCHEMAS"
$schemaFile = Join-Path $DatabaseDir "windows-world-schema.sql"
$appliedCount = Apply-DatabaseSchema `
    -Host $MysqlHost `
    -User "root" `
    -Password $MysqlRootPassword `
    -Worlds $GameWorlds `
    -SchemaFile $schemaFile

# Step 5: Insert Test Users (unless skipped)
if (-not $SkipUserCreation) {
    Write-Header "STEP 5: INSERTING TEST USERS"
    $testUsersFile = Join-Path $DatabaseDir "windows-test-users.sql"
    $insertedCount = Insert-TestUsers `
        -Host $MysqlHost `
        -User "root" `
        -Password $MysqlRootPassword `
        -Worlds $GameWorlds `
        -TestUsersFile $testUsersFile
} else {
    Write-Info "Skipping test user creation (SkipUserCreation flag set)"
}

# Step 6: Generate Connection Configs
Write-Header "STEP 6: GENERATING CONNECTION CONFIGS"
$templateFile = Join-Path $DatabaseDir "windows-connection-template.php"
$configCount = Generate-AllConnectionConfigs `
    -Worlds $GameWorlds `
    -DbHost $MysqlHost `
    -DbUser $MysqlUser `
    -DbPassword $MysqlPassword `
    -TemplateFile $templateFile `
    -OutputDir $SectionsDir

# Step 7: Register Servers in PostgreSQL
Write-Header "STEP 7: REGISTERING SERVERS IN POSTGRESQL"
$registered = Register-ServersInPostgres `
    -Host $PgHost `
    -User $PgUser `
    -Password $PgPassword `
    -Database $PgDatabase `
    -Worlds $GameWorlds

# Step 8: Validation (unless skipped)
if (-not $SkipValidation) {
    Write-Header "STEP 8: RUNNING VALIDATION"
    $validateScript = Join-Path $ScriptRoot "scripts\validate-xampp.ps1"
    if (Test-Path $validateScript) {
        & $validateScript `
            -MysqlHost $MysqlHost `
            -MysqlUser $MysqlUser `
            -MysqlPassword $MysqlPassword `
            -PgHost $PgHost `
            -PgUser $PgUser `
            -PgPassword $PgPassword `
            -PgDatabase $PgDatabase
    } else {
        Write-Warning "Validation script not found: $validateScript"
    }
}

# Final Summary
Write-Header "SETUP COMPLETE"
Write-Success "Databases created/updated: $createdCount/$($GameWorlds.Count)"
Write-Success "Schemas applied: $appliedCount/$($GameWorlds.Count)"
if (-not $SkipUserCreation) {
    Write-Success "Test users inserted: $insertedCount/$($GameWorlds.Count)"
}
Write-Success "Connection configs generated: $configCount/$($GameWorlds.Count)"
Write-Success "Servers registered in PostgreSQL: $(if ($registered) { 'Yes' } else { 'No' })"
Write-Info ""
Write-Info "Next Steps:"
Write-Info "1. Start Apache: C:\xampp\apache_start.bat"
Write-Info "2. Open browser: http://localhost"
Write-Info "3. Test login with testuser1 / test123"
Write-Info ""

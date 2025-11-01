# Windows 11 Environment Setup

## System Requirements

### Minimum Requirements
- Windows 11 Pro or Enterprise (required for Hyper-V)
- CPU: 4 cores (Intel/AMD with virtualization support)
- RAM: 16GB minimum, 32GB recommended
- Storage: 50GB free space (SSD recommended)
- Network: Stable internet connection

### Software Requirements
- Windows Subsystem for Linux 2 (WSL2)
- Docker Desktop for Windows
- Git for Windows
- Visual Studio Code or preferred IDE
- MySQL Client (MySQL Workbench)

## Step 1: Enable Virtualization

### Check if Virtualization is Enabled

1. Open Task Manager (Ctrl + Shift + Esc)
2. Go to Performance tab
3. Select CPU
4. Check if "Virtualization" shows "Enabled"

### If Not Enabled

1. Restart computer
2. Enter BIOS/UEFI (usually F2, F10, or Delete during boot)
3. Find "Virtualization Technology" or "Intel VT-x" or "AMD-V"
4. Enable it
5. Save and exit

## Step 2: Install WSL2

### Enable WSL

Open PowerShell as Administrator and run:

```powershell
# Enable Windows Subsystem for Linux
dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart

# Enable Virtual Machine Platform
dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart

# Restart computer
Restart-Computer
```

After restart, open PowerShell as Administrator:

```powershell
# Set WSL2 as default version
wsl --set-default-version 2

# Install Ubuntu (recommended distribution)
wsl --install -d Ubuntu-22.04
```

### Configure Ubuntu

1. Launch Ubuntu from Start menu
2. Create username and password when prompted
3. Update packages:

```bash
sudo apt update && sudo apt upgrade -y
```

## Step 3: Install Docker Desktop

### Download and Install

1. Download Docker Desktop from: https://www.docker.com/products/docker-desktop/
2. Run the installer
3. During installation:
   - Check "Use WSL 2 instead of Hyper-V"
   - Check "Add shortcut to desktop"
4. Restart computer after installation

### Configure Docker Desktop

1. Launch Docker Desktop
2. Go to Settings (gear icon)
3. General:
   - ✓ Use WSL 2 based engine
   - ✓ Start Docker Desktop when you log in
4. Resources → WSL Integration:
   - ✓ Enable integration with my default WSL distro
   - ✓ Enable integration with additional distros: Ubuntu-22.04
5. Resources → Advanced:
   - CPUs: 4 (or more if available)
   - Memory: 8GB minimum, 16GB recommended
   - Swap: 2GB
   - Disk image size: 100GB
6. Click "Apply & Restart"

### Verify Docker Installation

Open PowerShell or Ubuntu terminal:

```bash
# Check Docker version
docker --version
# Expected: Docker version 24.x.x or higher

# Check Docker Compose version
docker-compose --version
# Expected: Docker Compose version v2.x.x or higher

# Test Docker
docker run hello-world
# Should see "Hello from Docker!" message
```

## Step 4: Install Git

### Download and Install Git for Windows

1. Download from: https://git-scm.com/download/win
2. Run the installer
3. Recommended settings:
   - Default editor: Use Visual Studio Code (or preferred)
   - PATH environment: Git from the command line and 3rd party software
   - HTTPS transport: Use the OpenSSL library
   - Line ending conversions: Checkout Windows-style, commit Unix-style
   - Terminal emulator: Use MinTTY
   - Default pull behavior: Rebase

### Configure Git

Open PowerShell or Git Bash:

```bash
# Set your identity
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

# Set default branch name
git config --global init.defaultBranch main

# Enable credential caching
git config --global credential.helper wincred

# Verify configuration
git config --list
```

## Step 5: Install Development Tools

### Visual Studio Code

1. Download from: https://code.visualstudio.com/
2. Install with default settings
3. Recommended extensions:
   - Docker (Microsoft)
   - Remote - WSL (Microsoft)
   - PHP Intelephense
   - GitLens
   - YAML
   - Docker Compose

### MySQL Workbench (Optional but Recommended)

1. Download from: https://dev.mysql.com/downloads/workbench/
2. Install with default settings
3. Used for database management and schema visualization

## Step 6: Create Project Directory

### Windows Directory Structure

Open PowerShell:

```powershell
# Create project directory
mkdir C:\Projects
cd C:\Projects

# Create Travian project folder
mkdir TravianT4.6
cd TravianT4.6
```

### WSL Directory Structure

Open Ubuntu terminal:

```bash
# Create project directory in WSL
mkdir -p ~/projects/TravianT4.6
cd ~/projects/TravianT4.6

# Note: You can access this from Windows at:
# \\wsl$\Ubuntu-22.04\home\<username>\projects\TravianT4.6
```

**Recommended:** Use WSL directory for better Docker performance.

## Step 7: Configure Windows Firewall

### Allow Docker Ports

Open PowerShell as Administrator:

```powershell
# Allow HTTP (port 80)
New-NetFirewallRule -DisplayName "Docker HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow

# Allow HTTPS (port 443)
New-NetFirewallRule -DisplayName "Docker HTTPS" -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow

# Allow MySQL (port 3306) - for external access if needed
New-NetFirewallRule -DisplayName "Docker MySQL" -Direction Inbound -Protocol TCP -LocalPort 3306 -Action Allow

# Allow Redis (port 6379) - for external access if needed
New-NetFirewallRule -DisplayName "Docker Redis" -Direction Inbound -Protocol TCP -LocalPort 6379 -Action Allow
```

## Step 8: Install Additional Tools

### Composer (PHP Dependency Manager)

Download and run installer from: https://getcomposer.org/Composer-Setup.exe

Verify installation:

```bash
composer --version
```

### Node.js and npm (for build tools)

Download LTS version from: https://nodejs.org/

Verify installation:

```bash
node --version
npm --version
```

## Step 9: Configure Hosts File (for Local Development)

### Edit Hosts File

1. Open Notepad as Administrator
2. Open file: `C:\Windows\System32\drivers\etc\hosts`
3. Add entries:

```
127.0.0.1    travian.local
127.0.0.1    api.travian.local
127.0.0.1    testworld.travian.local
127.0.0.1    demo.travian.local
```

4. Save file

## Step 10: Verify Installation

### Complete Verification Checklist

Open PowerShell and run each command:

```powershell
# 1. Check WSL
wsl --list --verbose
# Should show Ubuntu-22.04 with VERSION 2

# 2. Check Docker
docker --version
docker-compose --version
docker ps
# Should show no errors

# 3. Check Git
git --version

# 4. Check Composer
composer --version

# 5. Check Node/npm
node --version
npm --version

# 6. Check virtualization
systeminfo | findstr /C:"Hyper-V"
# Should show enabled features
```

### Test Docker with Sample Container

```bash
# Pull and run nginx test
docker run -d -p 8080:80 --name test-nginx nginx:latest

# Check if accessible
# Open browser: http://localhost:8080
# Should see "Welcome to nginx!"

# Clean up
docker stop test-nginx
docker rm test-nginx
```

## Troubleshooting

### WSL2 Installation Issues

**Error: "WSL 2 requires an update to its kernel component"**

Solution:
1. Download WSL2 kernel update: https://aka.ms/wsl2kernel
2. Install the update
3. Restart computer

### Docker Desktop Won't Start

**Error: "Docker Desktop failed to start"**

Solution:
1. Ensure Hyper-V is enabled
2. Ensure virtualization is enabled in BIOS
3. Run as administrator:
   ```powershell
   bcdedit /set hypervisorlaunchtype auto
   ```
4. Restart computer

### Permission Issues

**Error: "Permission denied" when running Docker commands**

Solution:
1. Add your user to docker-users group:
   - Right-click "This PC" → Manage
   - Local Users and Groups → Groups → docker-users
   - Add your user
2. Log out and log back in

## Next Steps

Continue to [02-DOCKER-CONFIGURATION.md](02-DOCKER-CONFIGURATION.md) to configure Docker and Docker Compose for the Travian application.

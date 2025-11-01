# Windows 11, WSL2, and Docker Desktop Setup

## 🎯 Purpose

This guide walks you through setting up a proper Windows 11 development environment for running TravianT4.6 locally using Docker. By the end, you'll have:

- ✅ WSL2 (Windows Subsystem for Linux) properly configured
- ✅ Docker Desktop running with WSL2 backend
- ✅ GPU pass-through enabled (for AI features)
- ✅ Proper networking for local game hosting
- ✅ Development tools installed

**Estimated Time:** 2-3 hours

---

## 📋 Prerequisites

Before starting, verify you have:

- ✅ **Windows 11 Pro** (Build 22000 or higher)
  - Check: `Settings` → `System` → `About` → look for "Version"
  - Home edition works but requires extra steps (not covered here)
- ✅ **Administrator access** to your Windows machine
- ✅ **16GB RAM minimum** (32GB recommended for AI features)
- ✅ **100GB free disk space**
- ✅ **Internet connection** for downloading tools

**For AI Features (Optional):**
- ✅ NVIDIA GPU with 24GB VRAM (RTX 3090 Ti, Tesla P40, etc.)
- ✅ Latest NVIDIA drivers installed from nvidia.com

---

## Section 1: Enable WSL2

### What is WSL2?

**Windows Subsystem for Linux 2 (WSL2)** is a compatibility layer that lets you run a Linux kernel directly on Windows. Docker Desktop uses WSL2 as its backend, providing:

- Better performance than Hyper-V virtualization
- Direct file system access
- Native Linux tool compatibility
- Lower memory overhead

### Step 1.1: Enable Windows Features

Open **PowerShell as Administrator** and run:

```powershell
dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart

dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
```

**What this does:**
- Enables the Windows Subsystem for Linux feature
- Enables Virtual Machine Platform (required for WSL2)

### Step 1.2: Restart Your Computer

```powershell
shutdown /r /t 0
```

**⚠️ Important:** You MUST restart for these changes to take effect.

### Step 1.3: Download and Install WSL2 Linux Kernel Update

After restart, download the WSL2 kernel update:

1. Visit: https://aka.ms/wsl2kernel
2. Download: `wsl_update_x64.msi`
3. Run the installer
4. Click "Next" → "Next" → "Finish"

### Step 1.4: Set WSL2 as Default Version

Open **PowerShell as Administrator**:

```powershell
wsl --set-default-version 2
```

### Step 1.5: Install Ubuntu 22.04 LTS

```powershell
wsl --install -d Ubuntu-22.04
```

**What happens:**
- Downloads Ubuntu 22.04 from Microsoft Store
- Installs it as a WSL2 distribution
- Launches Ubuntu setup wizard

**First-time setup:**
1. Wait for "Installing, this may take a few minutes..."
2. Enter a **username** (lowercase, no spaces): `travian`
3. Enter a **password** (you'll need this for `sudo` commands)
4. Confirm password

**💡 Tip:** Your Windows drives are automatically mounted in WSL2 at `/mnt/c`, `/mnt/d`, etc.

### Step 1.6: Verify WSL2 Installation

```powershell
wsl --list --verbose
```

**Expected output:**
```
  NAME            STATE           VERSION
* Ubuntu-22.04    Running         2
```

**✅ Success criteria:** VERSION must be `2`, not `1`

**❌ If VERSION shows `1`:**
```powershell
wsl --set-version Ubuntu-22.04 2
```

---

## Section 2: Install Docker Desktop

### Step 2.1: Download Docker Desktop

1. Visit: https://www.docker.com/products/docker-desktop/
2. Click "Download for Windows"
3. Download: `Docker Desktop Installer.exe`
4. Run the installer

### Step 2.2: Installation Options

During installation, ensure these options are **checked**:

- ✅ **Use WSL 2 instead of Hyper-V** (should be default)
- ✅ **Add shortcut to desktop** (optional)

Click "Ok" to begin installation.

**⚠️ Installation may take 10-15 minutes**

### Step 2.3: Post-Installation Configuration

After installation completes:

1. Click "Close and restart"
2. Windows will restart
3. After restart, Docker Desktop will auto-launch
4. Accept the Docker Subscription Service Agreement
5. Skip the tutorial (or complete it if you're new to Docker)

### Step 2.4: Configure Docker Desktop for WSL2

1. Click the **Docker Desktop** system tray icon
2. Click **Settings** (gear icon)
3. Go to **General** tab:
   - ✅ **Use the WSL 2 based engine** (should be checked)
4. Go to **Resources** → **WSL Integration**:
   - ✅ **Enable integration with my default WSL distro**
   - ✅ **Ubuntu-22.04** (toggle on)
5. Click **Apply & Restart**

### Step 2.5: Verify Docker Installation

Open **Ubuntu 22.04** (from Start menu) and run:

```bash
docker --version
docker-compose --version
```

**Expected output:**
```
Docker version 24.0.7, build afdd53b
Docker Compose version v2.23.3-desktop.2
```

**Test Docker functionality:**

```bash
docker run hello-world
```

**Expected output:**
```
Hello from Docker!
This message shows that your installation appears to be working correctly.
...
```

**✅ Success:** If you see the "Hello from Docker!" message, Docker is working correctly.

---

## Section 3: Configure Docker Resources

### Why This Matters

Docker Desktop defaults to conservative resource limits. For TravianT4.6 with AI features, we need more resources.

### Step 3.1: Adjust Memory Allocation

1. Open **Docker Desktop Settings**
2. Go to **Resources** → **Advanced** (or **Resources** → **WSL Integration** → **Advanced**)

**Recommended settings:**

| Resource | Minimum | Recommended | For AI (500 NPCs) |
|----------|---------|-------------|-------------------|
| CPUs | 4 | 8 | 12+ |
| Memory | 8 GB | 16 GB | 24 GB |
| Swap | 2 GB | 4 GB | 8 GB |
| Disk image size | 100 GB | 200 GB | 500 GB |

**💡 Tip:** Leave 25% of your system RAM for Windows. If you have 32GB RAM, allocate max 24GB to Docker.

### Step 3.2: Enable File Sharing

1. Go to **Resources** → **File Sharing**
2. Ensure your project drive is listed (usually `C:\`)
3. If not listed, click **+ Add** and select your drive

### Step 3.3: Apply and Restart

Click **Apply & Restart** and wait for Docker to restart (~30 seconds).

---

## Section 4: GPU Support (For AI Features)

⚠️ **Skip this section if you don't have an NVIDIA GPU or don't plan to use AI features yet.**

### Prerequisites

- NVIDIA GPU with CUDA support (RTX 3090 Ti, Tesla P40, etc.)
- Latest NVIDIA drivers from nvidia.com
- CUDA Toolkit 12.0+ installed on Windows

### Step 4.1: Install NVIDIA Container Toolkit in WSL2

Open **Ubuntu 22.04** terminal:

```bash
distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
curl -fsSL https://nvidia.github.io/libnvidia-container/gpgkey | sudo gpg --dearmor -o /usr/share/keyrings/nvidia-container-toolkit-keyring.gpg
curl -s -L https://nvidia.github.io/libnvidia-container/$distribution/libnvidia-container.list | \
    sed 's#deb https://#deb [signed-by=/usr/share/keyrings/nvidia-container-toolkit-keyring.gpg] https://#g' | \
    sudo tee /etc/apt/sources.list.d/nvidia-container-toolkit.list

sudo apt-get update
sudo apt-get install -y nvidia-container-toolkit
```

### Step 4.2: Configure Docker for GPU

```bash
sudo nvidia-ctk runtime configure --runtime=docker
sudo systemctl restart docker
```

**⚠️ Note:** On WSL2, `systemctl` might not work. If it fails, restart Docker Desktop from Windows.

### Step 4.3: Verify GPU Access

```bash
docker run --rm --gpus all nvidia/cuda:12.0.0-base-ubuntu22.04 nvidia-smi
```

**Expected output:**
```
+-----------------------------------------------------------------------------+
| NVIDIA-SMI 535.129.03   Driver Version: 537.13       CUDA Version: 12.2     |
|-------------------------------+----------------------+----------------------+
| GPU  Name        Persistence-M| Bus-Id        Disp.A | Volatile Uncorr. ECC |
| Fan  Temp  Perf  Pwr:Usage/Cap|         Memory-Usage | GPU-Util  Compute M. |
|                               |                      |               MIG M. |
|===============================+======================+======================|
|   0  NVIDIA GeForce ...  Off  | 00000000:01:00.0  On |                  N/A |
| 30%   45C    P8    25W / 350W |    512MiB / 24576MiB |      2%      Default |
|                               |                      |                  N/A |
+-------------------------------+----------------------+----------------------+
```

**✅ Success:** If you see your GPU details, GPU pass-through is working!

**❌ If you see errors:**
- Verify NVIDIA drivers are installed on Windows (not in WSL2)
- Check Docker Desktop has WSL2 integration enabled
- Try restarting Docker Desktop

---

## Section 5: Install Development Tools

### Step 5.1: Update Ubuntu Packages

Open **Ubuntu 22.04** terminal:

```bash
sudo apt update && sudo apt upgrade -y
```

### Step 5.2: Install Essential Tools

```bash
sudo apt install -y \
    git \
    curl \
    wget \
    vim \
    nano \
    htop \
    net-tools \
    zip \
    unzip \
    build-essential \
    ca-certificates \
    software-properties-common
```

**What these tools do:**
- `git` - Version control (required for cloning repository)
- `curl/wget` - Downloading files
- `vim/nano` - Text editors
- `htop` - System monitoring
- `net-tools` - Network diagnostics
- `build-essential` - Compilers and build tools

### Step 5.3: Install MySQL Client (Optional but Recommended)

```bash
sudo apt install -y mysql-client
```

**Why install this?**
Allows you to connect to the MySQL Docker container from WSL2 terminal for debugging and manual queries.

### Step 5.4: Install PHP CLI (For Testing)

```bash
sudo apt install -y php8.1-cli php8.1-mysql php8.1-curl php8.1-xml php8.1-mbstring
```

**Why install this?**
Useful for testing PHP scripts outside Docker and running Composer commands.

---

## Section 6: Network Configuration

### Understanding Docker Networking on Windows

When running Docker Desktop on Windows 11:

- **From Windows:** Access containers via `localhost:PORT`
- **From WSL2:** Access containers via `localhost:PORT` OR Docker container names
- **From Internet:** Access via your **public IP** or **domain name** on port 5000

### Step 6.1: Verify Network Connectivity

**Test 1: Can WSL2 access Windows?**

From Ubuntu terminal:

```bash
ping -c 3 $(ip route | grep default | awk '{print $3}')
```

**Expected:** Successful pings to your Windows host IP

**Test 2: Can Windows access WSL2?**

From Windows PowerShell:

```powershell
wsl hostname -I
```

This shows your WSL2 IP address. Save it for later.

### Step 6.2: Configure Windows Firewall (If Needed)

If you plan to access the game from other devices on your network:

1. Open **Windows Defender Firewall with Advanced Security**
2. Click **Inbound Rules** → **New Rule**
3. Rule Type: **Port**
4. Protocol: **TCP**, Specific local ports: **5000**
5. Action: **Allow the connection**
6. Profile: **Private** (uncheck Public for security)
7. Name: `Travian Game Server`
8. Click **Finish**

**⚠️ Security Warning:** Only allow port 5000 on Private networks, not Public. For internet access, use Cloudflare Tunnel or ngrok (covered in Guide 12).

---

## Section 7: Troubleshooting Common Issues

### Issue 1: "WSL 2 requires an update to its kernel component"

**Solution:**
1. Download WSL2 kernel update: https://aka.ms/wsl2kernel
2. Install it
3. Restart Docker Desktop

### Issue 2: Docker Desktop won't start

**Symptoms:**
- Docker Desktop icon shows "Starting..." forever
- "Docker failed to start" error

**Solutions:**

**Solution A: Restart WSL**
```powershell
wsl --shutdown
```

Wait 10 seconds, then start Docker Desktop again.

**Solution B: Reset Docker Desktop**
1. Right-click Docker Desktop system tray icon
2. Click "Troubleshoot"
3. Click "Reset to factory defaults"
4. Click "Reset" and wait 5-10 minutes

**Solution C: Reinstall Docker Desktop**
1. Uninstall Docker Desktop from Windows Settings
2. Delete `C:\Program Files\Docker`
3. Delete `C:\Users\YourUser\AppData\Local\Docker`
4. Restart Windows
5. Reinstall Docker Desktop

### Issue 3: "The command 'docker' could not be found"

**In WSL2:**

```bash
sudo apt update
sudo apt install docker.io docker-compose
```

**Then verify Docker Desktop integration:**
1. Docker Desktop → Settings → Resources → WSL Integration
2. Enable integration with Ubuntu-22.04

### Issue 4: Permission denied when running Docker commands

**Symptoms:**
```
Got permission denied while trying to connect to the Docker daemon socket
```

**Solution:**

```bash
sudo usermod -aG docker $USER
```

**Then log out and back in to WSL2:**

```bash
exit
```

Close the Ubuntu window, reopen it, and try again.

### Issue 5: WSL2 uses too much memory

**Symptoms:**
- Windows becomes slow
- WSL2 process (Vmmem) using 8GB+ RAM

**Solution: Create `.wslconfig` file**

In Windows, create: `C:\Users\YourUsername\.wslconfig`

```ini
[wsl2]
memory=8GB
processors=4
swap=2GB
localhostForwarding=true
```

**Then restart WSL:**

```powershell
wsl --shutdown
```

Wait 10 seconds, then reopen Ubuntu.

### Issue 6: Docker containers can't access the internet

**Solution:**

Edit `/etc/resolv.conf` in WSL2:

```bash
sudo nano /etc/resolv.conf
```

Change to:
```
nameserver 8.8.8.8
nameserver 8.8.4.4
```

**Make it permanent:**

```bash
sudo nano /etc/wsl.conf
```

Add:
```ini
[network]
generateResolvConf = false
```

Restart WSL:
```powershell
wsl --shutdown
```

---

## Section 8: Performance Optimization

### Optimization 1: Use WSL2 File System for Project

**❌ Don't do this:**
```
Project location: C:\Users\YourName\Projects\TravianT4.6
Working from: /mnt/c/Users/YourName/Projects/TravianT4.6
```

**Why this is slow:**
- WSL2 accessing Windows file system is 10-100x slower
- File watching doesn't work properly
- Docker volumes are significantly slower

**✅ Do this instead:**
```
Project location: /home/travian/Projects/TravianT4.6
```

**Why this is fast:**
- Native Linux file system (ext4)
- 10-100x faster I/O
- Proper file watching
- Fast Docker volumes

**How to clone project:**

```bash
cd ~
mkdir -p Projects
cd Projects
git clone https://github.com/Ghenghis/Travian-Solo.git TravianT4.6
cd TravianT4.6
```

**Accessing WSL2 files from Windows:**

In Windows Explorer, type: `\\wsl$\Ubuntu-22.04\home\travian\Projects\TravianT4.6`

Or use VS Code's **Remote - WSL** extension to edit files directly in WSL2.

### Optimization 2: Enable BuildKit for Docker

Add to `~/.bashrc` in WSL2:

```bash
echo 'export DOCKER_BUILDKIT=1' >> ~/.bashrc
source ~/.bashrc
```

**Benefits:**
- Faster Docker builds
- Better caching
- Parallel build steps

### Optimization 3: Increase File Watch Limit

Linux has a default limit on the number of files that can be watched. Increase it:

```bash
echo 'fs.inotify.max_user_watches=524288' | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

---

## ✅ Verification Checklist

Before proceeding to the next guide, verify:

- [ ] WSL2 is installed and set as default version
- [ ] Ubuntu 22.04 is installed as a WSL2 distribution
- [ ] Docker Desktop is running
- [ ] Docker Desktop is using WSL2 backend
- [ ] Docker Desktop has WSL2 integration enabled for Ubuntu-22.04
- [ ] `docker --version` works in Ubuntu terminal
- [ ] `docker run hello-world` succeeds
- [ ] Docker has 16GB+ memory allocated (or appropriate for your system)
- [ ] (Optional) GPU pass-through verified with `nvidia-smi`
- [ ] Essential development tools installed (git, curl, vim, etc.)
- [ ] MySQL client installed
- [ ] Project will be cloned to WSL2 file system (not /mnt/c)

**Verification command:**

```bash
docker info | grep -i "Operating System"
docker info | grep -i "WSL"
docker info | grep -i "Memory"
```

**Expected output includes:**
```
Operating System: Docker Desktop
OSType: linux
Memory: 16GiB (or your configured amount)
```

---

## 🚀 Next Steps

**Congratulations!** You now have a fully configured Windows 11 development environment with WSL2 and Docker Desktop.

**Next guide:** [PROJECT-BOOTSTRAP.md](./PROJECT-BOOTSTRAP.md)

This will walk you through:
- Cloning the TravianT4.6 repository
- Setting up the directory structure
- Creating environment variable files
- Understanding the project architecture

---

**Last Updated:** October 29, 2025  
**Estimated Completion Time:** 2-3 hours  
**Difficulty:** Intermediate

# TravianT4.6 Local Setup - Master Overview

## 🎯 Purpose of This Documentation Suite

This documentation suite will guide you through transforming the TravianT4.6 project into a **production-ready, Docker-based, Windows 11 local deployment** with:

- ✅ Fully functional registration and login system
- ✅ Real email delivery to external addresses (even for local hosting)
- ✅ Working payment systems (Stripe/PayPal sandbox)
- ✅ 50-500 AI-driven NPC agents using local LLMs
- ✅ Complete database schemas with no missing tables or columns
- ✅ Proper port configuration and network setup

## 🚨 Critical Issues Found (What Went Wrong)

Previous implementation attempts encountered these severe problems:

### 1. **Database Architecture Confusion**
- ❌ **What was wrong:** Mixed PostgreSQL and MySQL setup, unclear which to use
- ❌ **Port confusion:** MySQL running on port 3307 instead of standard 3306
- ❌ **Missing columns:** `activation.used` column missing, causing registration failures
- ❌ **Missing databases:** Game world databases (`travian_testworld`, `travian_demo`) not created
- ❌ **Incomplete schema:** Only 4 global tables imported, missing 100+ game tables

### 2. **Missing Configuration Files**
- ❌ **What was wrong:** `sections/servers/testworld/include/connection.php` missing
- ❌ **Why it failed:** Each game world needs its own database connection file
- ❌ **Impact:** Login attempts failed with "userDoesNotExists" errors

### 3. **Broken Registration → Activation → Login Flow**
- ❌ **Registration:** Schema mismatches prevented user creation
- ❌ **Activation:** Missing `used` column caused activation handler to crash
- ❌ **Login:** Missing world databases prevented session creation
- ❌ **Result:** Complete authentication system non-functional

### 4. **Email & Payment Systems Not Configured**
- ❌ **Email:** No SMTP configuration, activation emails never sent
- ❌ **Payments:** No Stripe/PayPal integration, premium features inaccessible
- ❌ **Misconception:** Thought local hosting meant no external services

## 📚 Documentation Structure

This suite contains **18 comprehensive guides** organized into 6 tracks:

### **Track 1: Foundation & Infrastructure (Guides 1-4)**
Required to establish proper development environment.

| Guide | File | Purpose | Estimated Time |
|-------|------|---------|----------------|
| 1 | `LOCAL-OVERVIEW.md` | This document - master roadmap | 15 min read |
| 2 | `WINDOWS-WSL2-DOCKER.md` | Windows 11, WSL2, Docker Desktop setup with GPU | 2-3 hours |
| 3 | `PROJECT-BOOTSTRAP.md` | Repository cloning, directory structure, environment variables | 1 hour |
| 4 | `MYSQL-INFRASTRUCTURE.md` | MySQL 8 Docker container, port 3306 standardization | 1-2 hours |

### **Track 2: Database Recovery (Guides 5-8)**
Critical for fixing schema issues and creating proper database structure.

| Guide | File | Purpose | Estimated Time |
|-------|------|---------|----------------|
| 5 | `GLOBAL-SCHEMA-RESTORE.md` | Import Travian-Solo schema, fix activation table | 1-2 hours |
| 6 | `GAME-WORLD-DATABASES.md` | Create game world databases with complete T4.4 schema | 2-3 hours |
| 7 | `CONNECTION-PHP-GENERATOR.md` | Auto-generate connection.php for each world | 30 min |
| 8 | `REDIS-MEMCACHED.md` | Caching layer for performance optimization | 1 hour |

### **Track 3: Authentication System Repair (Guides 9-11)**
Fix the broken registration/login flow to enable user accounts.

| Guide | File | Purpose | Estimated Time |
|-------|------|---------|----------------|
| 9 | `REGISTRATION-FLOW-REPAIR.md` | Fix PHP controllers, schema validation, registration API | 2-3 hours |
| 10 | `EMAIL-DELIVERY.md` | SMTP setup for real email delivery (SendGrid/MailHog) | 1-2 hours |
| 11 | `LOGIN-SESSION-STABILITY.md` | Fix userDoesNotExists, session management, password hashing | 2-3 hours |

### **Track 4: External Services Integration (Guides 12-13)**
Enable payment processing and orchestrate all services together.

| Guide | File | Purpose | Estimated Time |
|-------|------|---------|----------------|
| 12 | `PAYMENTS-LOCAL.md` | Stripe/PayPal sandbox, webhook tunneling (ngrok) | 2-3 hours |
| 13 | `DOCKER-COMPOSE-ORCHESTRATION.md` | Multi-service stack orchestration | 2-3 hours |

### **Track 5: Production Readiness (Guides 14-16)**
Harden, test, and prepare for production deployment.

| Guide | File | Purpose | Estimated Time |
|-------|------|---------|----------------|
| 14 | `OBSERVABILITY-TESTING.md` | PHPUnit tests, Postman API tests, monitoring | 3-4 hours |
| 15 | `HARDENING-SECURITY.md` | Security config, TLS, rate limiting, backups | 2-3 hours |
| 16 | `OPERATIONS-RUNBOOK.md` | Startup/shutdown procedures, maintenance tasks | 1 hour |

### **Track 6: AI Framework Integration (Guides 17-18)**
⚠️ **ONLY START AFTER REGISTRATION/LOGIN ARE WORKING**

| Guide | File | Purpose | Estimated Time |
|-------|------|---------|----------------|
| 17 | `AI-INTEGRATION-PRECHECK.md` | Gating criteria, API hooks, data contracts | 2 hours |
| 18 | `AI-FRAMEWORK-ACTIVATION.md` | Link existing AI docs, GPU pipeline, NPC system | 5-10 hours |

## 🛤️ Recommended Reading Paths

### **Path A: Quick Start (Minimum Viable Setup)**
For getting basic functionality working as fast as possible:

1. `LOCAL-OVERVIEW.md` (this document)
2. `WINDOWS-WSL2-DOCKER.md` (sections 1-4 only)
3. `PROJECT-BOOTSTRAP.md` (complete)
4. `MYSQL-INFRASTRUCTURE.md` (complete)
5. `GLOBAL-SCHEMA-RESTORE.md` (complete)
6. `GAME-WORLD-DATABASES.md` (testworld only)
7. `CONNECTION-PHP-GENERATOR.md` (complete)
8. `REGISTRATION-FLOW-REPAIR.md` (complete)
9. `LOGIN-SESSION-STABILITY.md` (complete)

**Result:** Working registration/login on a single test world (4-6 hours)

### **Path B: Production Ready (Full Deployment)**
For complete, production-ready local hosting:

Follow all guides in order (Tracks 1-5), estimated 30-40 hours total.

### **Path C: AI-Enhanced Game (Full Project)**
For the complete AI-driven solo-play game:

Follow all guides in order (Tracks 1-6), estimated 45-60 hours total.

## 🎓 Prerequisites

Before starting this journey, ensure you have:

### **Required Knowledge**
- ✅ Basic command line usage (Windows PowerShell, WSL2 bash)
- ✅ Basic understanding of Docker concepts (containers, volumes, networks)
- ✅ Basic SQL knowledge (SELECT, INSERT, CREATE TABLE)
- ✅ Basic PHP understanding (reading code, not necessarily writing)

### **Required Hardware**
- ✅ Windows 11 Pro (for Docker Desktop)
- ✅ 16GB RAM minimum (32GB recommended for AI NPCs)
- ✅ 100GB free disk space minimum
- ✅ **For AI Features:** RTX 3090 Ti or Tesla P40 with 24GB VRAM

### **Required Software**
- ✅ Windows 11 Pro (Build 22000 or higher)
- ✅ WSL2 enabled
- ✅ Docker Desktop for Windows
- ✅ Git for Windows
- ✅ Text editor (VS Code recommended)

### **Optional (For AI Features)**
- ✅ NVIDIA GPU with CUDA support
- ✅ NVIDIA Container Toolkit for Docker
- ✅ Local LLM runtime (Ollama, LM Studio, or vLLM)

## 🔧 Architecture Overview

### **What We're Building**

```
┌─────────────────────────────────────────────────────────────────┐
│                         Windows 11 Host                          │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                    Docker Desktop (WSL2)                  │  │
│  │                                                            │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌───────────────┐  │  │
│  │  │  PHP-FPM 8.2 │  │  Nginx 1.25  │  │  MySQL 8.0    │  │  │
│  │  │  (Game Logic)│◄─┤  (Web Server)│  │  (port 3306)  │  │  │
│  │  └──────────────┘  └──────────────┘  └───────────────┘  │  │
│  │         ▲                                      ▲          │  │
│  │         │                                      │          │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌───────────────┐  │  │
│  │  │  Redis 7.2   │  │  MailHog     │  │  TaskWorker   │  │  │
│  │  │  (Caching)   │  │  (SMTP Mock) │  │  (Cron Jobs)  │  │  │
│  │  └──────────────┘  └──────────────┘  └───────────────┘  │  │
│  │                                                            │  │
│  │  ┌───────────────────────────────────────────────────┐   │  │
│  │  │  AI NPC Engine (Post-Auth Integration)            │   │  │
│  │  │  - Local LLM (Ollama/vLLM on GPU)                 │   │  │
│  │  │  - NPC Scheduler (50-500 agents)                  │   │  │
│  │  │  - Hybrid AI (95% rules, 5% LLM)                  │   │  │
│  │  └───────────────────────────────────────────────────┘   │  │
│  └──────────────────────────────────────────────────────────┘  │
│                             ▲                                   │
│                             │                                   │
│  ┌──────────────────────────┴───────────────────────────────┐  │
│  │  External Services (via Internet)                         │  │
│  │  - SendGrid/Postfix (Real Email Delivery)                │  │
│  │  - Stripe/PayPal Sandbox (Payment Webhooks via Ngrok)    │  │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### **Database Architecture**

```
MySQL 8.0 Container (Port 3306)
├── travian_global (Global/API Database)
│   ├── gameServers (World list)
│   ├── configurations (App settings)
│   ├── activation (Email verification)
│   ├── banIP (Security)
│   └── [Other global tables from Travian-Solo schema]
│
├── travian_testworld (Test World - 100x Speed)
│   ├── users (Player accounts for this world)
│   ├── villages (Player settlements)
│   ├── troops (Military units)
│   ├── buildings (Structures)
│   └── [100+ game tables from T4.4 schema]
│
└── travian_demo (Demo World - 5x Speed)
    ├── users (Player accounts for this world)
    ├── villages (Player settlements)
    ├── troops (Military units)
    ├── buildings (Structures)
    └── [100+ game tables from T4.4 schema]
```

## 🔑 Key Concepts & Corrections

### **1. Local Hosting ≠ No External Services**

**❌ Common Misconception:**
> "If I'm hosting locally, I can't use email or payment services."

**✅ Correct Understanding:**
> Local hosting means your **game server** runs on your Windows 11 machine. Your server can still **make outbound connections** to external services like SendGrid (email) and Stripe (payments). Users connect to your local server via your public IP or domain, and the server communicates with external APIs on their behalf.

### **2. MySQL Port Standardization**

**❌ What Was Wrong:**
> MySQL configured on non-standard port 3307, causing connection confusion.

**✅ Why Port 3306 Matters:**
> Port 3306 is the universal MySQL standard. All PHP libraries, connection strings, and documentation assume 3306. Using 3307 creates configuration nightmares and connection errors.

**✅ Correct Approach:**
> Always use MySQL on port 3306 in Docker. If you have conflicts with existing MySQL installations on Windows, stop the Windows service or use Docker's port mapping.

### **3. Database Per Game World**

**❌ What Was Wrong:**
> Only created `travian_global` database, missing individual world databases.

**✅ Why This Failed:**
> Travian's architecture uses one global database for the API/server list, and **separate databases for each game world**. When a player logs into "testworld", the game looks for a `travian_testworld` database. Missing this database = "userDoesNotExists" errors.

**✅ Correct Approach:**
```
travian_global      → API, server list, activations
travian_testworld   → Game world "testworld" data
travian_demo        → Game world "demo" data
travian_[worldId]   → Pattern for any new world
```

### **4. Schema Completeness**

**❌ What Was Wrong:**
> Only imported 4 tables (gameServers, configurations, activation, banIP).

**✅ Why This Failed:**
> The Travian-Solo repository contains a **complete MySQL schema** with 100+ tables needed for gameplay. Without these tables, features like:
> - User accounts
> - Villages
> - Troops
> - Buildings
> - Alliances
> - Messages
> ...all fail catastrophically.

**✅ Correct Approach:**
> Import the **complete Travian-Solo schema** from `/tmp/Travian-Solo/database/main.sql`, which includes all necessary tables with proper indexes, foreign keys, and default data.

### **5. Connection.php Files**

**❌ What Was Wrong:**
> Missing `sections/servers/{worldId}/include/connection.php` files.

**✅ Why This Failed:**
> Each game world needs its own connection configuration file that tells the game code:
> - Which database to connect to (`travian_testworld`)
> - Which MySQL host/port to use
> - What credentials to authenticate with

Without this file, the game can't connect to its world database.

**✅ Correct Approach:**
> Generate connection.php files automatically from a template using environment variables. Guide 7 provides a PHP script to do this.

## 📊 Project Completion Checklist

Use this checklist to track your progress:

### **Phase 1: Infrastructure (Guides 1-4)**
- [ ] Windows 11 Pro installed and updated
- [ ] WSL2 enabled and updated to WSL2
- [ ] Docker Desktop installed and running
- [ ] Project cloned to accessible location
- [ ] `.env` file created with all required variables
- [ ] MySQL 8.0 container running on port 3306
- [ ] Can connect to MySQL from Windows and WSL2

### **Phase 2: Database Recovery (Guides 5-8)**
- [ ] `travian_global` database created
- [ ] Travian-Solo schema imported to global database
- [ ] `activation.used` column added (TINYINT)
- [ ] `travian_testworld` database created
- [ ] T4.4 game schema imported to testworld
- [ ] `travian_demo` database created
- [ ] T4.4 game schema imported to demo
- [ ] Connection.php files generated for all worlds
- [ ] Redis container running and accessible

### **Phase 3: Authentication (Guides 9-11)**
- [ ] Can access registration page via browser
- [ ] Can submit registration form without errors
- [ ] Registration inserts user into `activation` table
- [ ] Activation email sends successfully (dev or production)
- [ ] Can click activation link and activate account
- [ ] Activation creates user in world database
- [ ] Can log in with activated account
- [ ] Login creates session and redirects to game
- [ ] Session persists across page reloads

### **Phase 4: External Services (Guides 12-13)**
- [ ] MailHog receiving test emails (dev mode)
- [ ] OR SendGrid/Postfix sending real emails (production mode)
- [ ] Stripe sandbox configured with test keys
- [ ] Ngrok tunnel exposing local server for webhooks
- [ ] Test payment successfully processes
- [ ] Webhook received and verified
- [ ] Docker Compose orchestrating all services
- [ ] All containers start without errors
- [ ] Can access game from browser

### **Phase 5: Production Ready (Guides 14-16)**
- [ ] PHPUnit tests passing
- [ ] Postman API test collection passing
- [ ] Monitoring stack deployed (optional)
- [ ] TLS/SSL configured (optional for local)
- [ ] Rate limiting configured
- [ ] Database backup strategy implemented
- [ ] Startup/shutdown scripts tested
- [ ] Cron jobs scheduled for TaskWorker

### **Phase 6: AI Integration (Guides 17-18)**
⚠️ **Only start after Phases 1-3 are 100% complete**

- [ ] All Phase 3 checklist items completed
- [ ] GPU accessible in Docker (NVIDIA Container Toolkit)
- [ ] Local LLM running (Ollama/vLLM)
- [ ] LLM API endpoint tested
- [ ] Read all 20 docs from `docs/AI/` folder
- [ ] NPC scheduler deployed
- [ ] First AI NPC account created
- [ ] AI NPC performs basic actions (login, build, farm)
- [ ] 50 NPCs running simultaneously
- [ ] Performance <200ms response time
- [ ] 95/5 rule-based vs LLM ratio verified

## 🚀 Getting Started

**Ready to begin?** Start with Guide 2:

➡️ **[WINDOWS-WSL2-DOCKER.md](./WINDOWS-WSL2-DOCKER.md)**

This guide will set up your Windows 11 development environment with WSL2, Docker Desktop, and GPU support for AI features.

---

## 📞 Support & Troubleshooting

### **Common Issues**

**Issue:** "I'm lost, where do I start?"
- **Solution:** Follow Path A (Quick Start) from the "Recommended Reading Paths" section above.

**Issue:** "Docker Desktop won't start on Windows 11"
- **Solution:** See Guide 2, Section "Troubleshooting Docker Desktop Issues"

**Issue:** "MySQL container keeps crashing"
- **Solution:** See Guide 4, Section "MySQL Container Health Checks"

**Issue:** "Registration still doesn't work after following guides"
- **Solution:** See Guide 9, Section "Debugging Registration Failures"

**Issue:** "userDoesNotExists error persists"
- **Solution:** See Guide 11, Section "Diagnosing User Lookup Failures"

### **Getting Help**

1. Check the specific guide's "Troubleshooting" section
2. Verify all checklist items for that phase are complete
3. Review error logs (guides include log analysis sections)
4. Search the Travian-Solo repository GitHub issues
5. Post detailed error logs and configuration to appropriate forums

---

## 📖 Document Conventions

Throughout this documentation suite, you'll see these symbols:

- ✅ **Correct approach** or completed item
- ❌ **Incorrect approach** or what went wrong
- ⚠️ **Warning** or critical information
- 💡 **Tip** or best practice
- 🔍 **Deep dive** or technical explanation
- 📝 **Note** or additional context
- 🚀 **Performance tip** or optimization
- 🔐 **Security consideration**

Code blocks are labeled:
- `bash` - Run in WSL2/Linux terminal
- `powershell` - Run in Windows PowerShell
- `sql` - Execute in MySQL client
- `php` - PHP code
- `yaml` - Configuration files (docker-compose, etc.)
- `env` - Environment variable files

---

## 🎯 Success Criteria

You'll know you've successfully completed this project when:

1. ✅ You can register a new account via the web interface
2. ✅ You receive an activation email (real or MailHog)
3. ✅ You can activate the account via the email link
4. ✅ You can log in with the activated account
5. ✅ You can access the game world interface
6. ✅ (AI Phase) AI NPCs are playing alongside you

**Time Estimate:**
- Quick Start (Path A): 4-6 hours
- Production Ready (Path B): 30-40 hours
- AI-Enhanced (Path C): 45-60 hours

---

**Last Updated:** October 29, 2025  
**Version:** 1.0.0  
**Project:** TravianT4.6 Local Docker/Windows 11 Setup

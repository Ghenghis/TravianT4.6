# TravianT4.6 - Production Deployment Guide

## Overview

This documentation set provides complete instructions for deploying TravianT4.6 multiplayer strategy game on Windows 11 using Docker, MySQL, and enterprise-grade practices.

## Documentation Structure

1. **[01-WINDOWS-SETUP.md](01-WINDOWS-SETUP.md)** - Windows 11 prerequisites and environment setup
2. **[02-DOCKER-CONFIGURATION.md](02-DOCKER-CONFIGURATION.md)** - Docker and Docker Compose setup
3. **[03-DATABASE-SETUP.md](03-DATABASE-SETUP.md)** - MySQL database configuration and schema import
4. **[04-APPLICATION-CONFIGURATION.md](04-APPLICATION-CONFIGURATION.md)** - Application settings and environment variables
5. **[05-GIT-GITHUB-WORKFLOW.md](05-GIT-GITHUB-WORKFLOW.md)** - Version control and CI/CD setup
6. **[06-PRODUCTION-DEPLOYMENT.md](06-PRODUCTION-DEPLOYMENT.md)** - Production deployment and scaling
7. **[07-SECURITY-HARDENING.md](07-SECURITY-HARDENING.md)** - Security best practices
8. **[08-MONITORING-MAINTENANCE.md](08-MONITORING-MAINTENANCE.md)** - Monitoring, logging, and maintenance
9. **[09-TROUBLESHOOTING.md](09-TROUBLESHOOTING.md)** - Common issues and solutions

## Architecture Overview

### Technology Stack

- **Frontend**: Angular (pre-compiled)
- **Backend API**: PHP 8.2 with FastRoute
- **Game Engine**: PHP multi-world architecture
- **Database**: MySQL 8.0 (Global + Per-World databases)
- **Cache**: Redis
- **Email**: PHPMailer / SendinBlue
- **Web Server**: Nginx + PHP-FPM
- **Background Workers**: Task worker service
- **Containerization**: Docker & Docker Compose

### System Components

```
┌─────────────────────────────────────────────────────────┐
│                    Nginx (Reverse Proxy)                │
│                    SSL Termination                       │
└────────────┬────────────────────────────────────────────┘
             │
             ├──────────► Angular Frontend (Static Files)
             │
             ├──────────► PHP-FPM API (/v1/*)
             │                  │
             │                  ├──► Global MySQL DB
             │                  │    (servers, activation, etc.)
             │                  │
             │                  └──► Per-World MySQL DBs
             │                       (users, villages, resources)
             │
             └──────────► Game World PHP Applications
                               │
                               ├──► Redis Cache
                               ├──► Task Worker
                               └──► Mail Service
```

### Database Architecture

1. **Global Database** (PostgreSQL on Replit or MySQL for production)
   - `gameServers` - Server configurations
   - `activation` - User registrations
   - `configurations` - Global settings
   - `banIP` - IP blocks
   - `email_blacklist` - Email filtering
   - `mailserver` - Email queue
   - `passwordRecovery` - Password reset tokens

2. **Per-World Databases** (MySQL - one per game world)
   - 90+ tables including:
   - `users` - Player accounts
   - `villages` - Player villages
   - `units` - Military units
   - `alliances` - Alliance data
   - `marketplace` - Trading system
   - And many more...

## Quick Start

For a quick development setup:

```bash
# 1. Clone the repository
git clone https://github.com/your-org/travian-t4.6.git
cd travian-t4.6

# 2. Copy environment template
cp .env.example .env

# 3. Start Docker containers
docker-compose up -d

# 4. Import database schemas
docker-compose exec mysql mysql -u root -p < main.sql

# 5. Access the application
# http://localhost
```

For detailed production deployment, follow the documentation in order.

## Prerequisites

- Windows 11 Pro/Enterprise (for Hyper-V)
- 16GB+ RAM
- 50GB+ free disk space
- Internet connection
- Domain name (for production)
- SSL certificate (for production)

## Support

For issues and questions:
- Check [Troubleshooting Guide](09-TROUBLESHOOTING.md)
- Review application logs
- Check Docker container status

## License

This documentation is provided as-is for deployment of TravianT4.6.

# TravianT4.6 - Documentation Hub

## Overview
This project implements a Travian T4.6 game server with a PHP backend and an Angular frontend, designed for quick cloud deployment on Replit using PostgreSQL. Its primary goal is to provide a robust game server environment, supporting both rapid prototyping and production-ready local hosting. A major new feature transforms Travian into an AI-driven solo-play game, enabling the simulation of 50-500 NPC agents using local LLMs. The project also includes a complete production-ready Docker infrastructure with advanced monitoring, backup automation, and robust security hardening.

## Documentation Roadmap Status
**Phase 1 (Discovery & Inventory): ✅ COMPLETE** - 4/4 documents
- 01-PROJECT-INVENTORY.md (49,476 files, 32,501 PHP LOC verified)
- 02-TECH-STACK-MATRIX.md (verified dependencies)
- 03-ARCHITECTURE-OVERVIEW.md (C4 diagrams)
- 04-DEPENDENCY-GRAPH.md (zero circular dependencies)

**Phase 2 (Deep Code Analysis): ✅ COMPLETE** - 5/5 documents
- 05-CODE-QUALITY-ANALYSIS.md (quality score 6.2/10)
- 06-PERFORMANCE-ANALYSIS.md (97% speedup possible)
- 07-API-ENDPOINT-CATALOG.md (150+ endpoints)
- 08-DATABASE-SCHEMA-DEEP-DIVE.md (PostgreSQL 11 tables)
- 08b-MYSQL-SCHEMA-ANALYSIS.md (MySQL 90 tables, dual-database architecture)

**Overall Progress**: 9/35 documents complete (26%)
**All documentation available in**: `docs/completion/`

## User Preferences
I prefer clear and concise information. Please prioritize core functionalities and architectural decisions. When suggesting changes or making modifications, explain the reasoning and impact. I prefer an iterative development approach, focusing on getting core features working before optimizing. Do not make changes to files or folders without explicit approval, especially for critical configuration or game logic files. I value reproducible setups and comprehensive documentation for complex integrations.

## System Architecture
The application is structured around a PHP backend (RESTful API), an Angular frontend, and a PostgreSQL database.

### UI/UX Decisions
The Angular frontend is pre-compiled and served directly, communicating with the backend via `/v1/` API endpoints. Language mapping intelligently handles various locale codes.

### Technical Implementations
- **Web Server:** PHP's built-in server routes static files and API requests via `router.php`.
- **Backend:** PHP 8.2 with Composer.
- **Frontend:** Pre-compiled Angular application, patched for same-domain API calls.
- **Database:** PostgreSQL (Replit's Neon-backed) with SSL enforcement, migrated from MySQL. Backend handles both camelCase and lowercase column names for compatibility.
- **Pathing:** All hardcoded absolute paths converted to relative.
- **GeoIP:** Polyfill for GeoIP functionality.
- **Game Engine:** Travian game engine deployed with a universal router for `.php` files.
- **Email System:** Implemented using Brevo Transactional Email API.
- **AI-NPC Automation:** Integrates AI for NPC decision-making, using a hybrid rule-based (95%) and LLM-based (5%) approach for efficient response times. Features include World Orchestration, Spawn Planning/Scheduling, NPC Initialization, and a sophisticated AI Decision Engine with personality traits and difficulty scaling.
- **Docker Infrastructure:** Multi-stage Docker builds for PHP-FPM and Nginx, with a modular `docker-compose` stack for core services, monitoring, and maintenance. Supports dual-GPU for LLM inference.
- **Security:** Implements secrets management (zero-downtime rotation), 4-tier network segmentation, TLS automation, ModSecurity WAF (OWASP CRS 4.0), CORS/CSRF protection, SQLi/XSS auditing, structured logging (Monolog), and audit trails.

### Feature Specifications
- **User Management:** Functional registration and activation flows.
- **Server Listing:** Game server list displays data fetched from the API.
- **Test Accounts:** Pre-populated accounts for testing.
- **AI-NPCs:** Comprehensive system for generating and managing AI-driven NPCs, including world generation, spawn management, and various automation profiles (farming, building, training, defense, away mode).
- **Monitoring:** Prometheus with Grafana for database health, worker throughput, and LLM latency, including 6 exporters and alert rules.
- **Backup Automation:** Automated daily backups with 3-tier retention for PostgreSQL and MySQL.

### System Design Choices
- **Deployment Flexibility:** Supports Replit cloud, Windows Docker, and XAMPP production setups (Windows 11).
- **Modular Structure:** Code organized into distinct sections (e.g., `angularIndex/`, `sections/api/`).
- **Environment Variables:** Critical configurations managed via Replit environment variables or Windows .env files.
- **AI Decision Making:** Hybrid rule-based and LLM approach for efficiency.
- **Feature Flags:** 3-tier system (Server/Player/AI-level) with Redis caching.
- **Spawn Algorithms:** Three distinct algorithms for NPC placement (quadrant_balanced, random_scatter, kingdom_clustering).
- **Progressive Spawning:** NPCs are spawned in batches over time.
- **Network Isolation:** 4-tier network segmentation in Docker for enhanced security.
- **Logging & Audit Trails:** Structured logging with correlation IDs and PostgreSQL audit events.
- **XAMPP Deployment:** Native Windows deployment option using Apache, MySQL 8.0, PostgreSQL 14 (addon) with automated PowerShell provisioning scripts.

## External Dependencies
- **PostgreSQL:** Replit's Neon-backed database, or XAMPP PostgreSQL 14 addon for Windows.
- **MySQL:** XAMPP MySQL 8.0 for Windows local deployment (per-world game databases).
- **Composer:** PHP dependency manager.
- **Redis:** System dependency (for caching and feature flags).
- **GeoIP:** System dependency.
- **Brevo Transactional Email API (formerly SendinBlue):** For email notifications.
- **FastRoute:** PHP routing library.
- **PHPMailer:** Email sending library.
- **reCAPTCHA:** For security.
- **Twig:** Templating engine.
- **Guzzle HTTP client:** Used by `TaskWorker/`.
- **Cloudflare SDK:** Used by `TaskWorker/`.
- **Discord Webhooks:** For system notifications.
- **Prometheus:** Monitoring system.
- **Grafana:** Data visualization and dashboards.
- **Loki & Promtail:** Centralized log aggregation.
- **Ollama / vLLM:** Local LLM APIs for AI inference.
- **ModSecurity WAF:** For web application firewall protection.
- **Let's Encrypt (certbot):** For TLS/SSL automation.
- **Trivy, hadolint:** Docker image scanning and linting.

## Deployment Documentation
- **WINDOWS-DEPLOYMENT-GUIDE.md** - Docker deployment on Windows 11 (650+ lines)
- **XAMPP-DEPLOYMENT-GUIDE.md** - XAMPP deployment on Windows 11 (4,539 lines, enterprise-grade)
- **XAMPP-QUICKSTART.md** - XAMPP quick reference (313 lines, 5-minute setup)
- **XAMPP-SCRIPTS-REFERENCE.md** - PowerShell automation scripts documentation (comprehensive technical reference)
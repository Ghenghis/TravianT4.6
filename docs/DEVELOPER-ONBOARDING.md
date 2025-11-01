# Developer Onboarding Guide - TravianT4.6

## Welcome

Welcome to the TravianT4.6 development team! This guide will help you set up your local development environment, understand the project structure, and contribute effectively to the codebase.

**Project Overview:**

TravianT4.6 is a multiplayer browser-based game server featuring AI-powered NPCs with LLM decision-making capabilities. The system handles 1000+ concurrent players and 500+ AI NPCs per game world, providing realistic gameplay through a hybrid rule-based + LLM architecture.

**What You'll Learn:**
- Setting up your local development environment
- Understanding the project structure and architecture
- Development workflow and best practices
- Testing and debugging techniques
- Contributing code with confidence

**Target Audience:** New developers joining the team

**Estimated Setup Time:** 2-3 hours

---

## Prerequisites

Before starting, ensure you have the following installed:

### Required Software

| Software | Version | Purpose | Download |
|----------|---------|---------|----------|
| **Windows 11** | Pro/Enterprise | Host OS | Pre-installed |
| **WSL2** | Latest | Linux environment | [Microsoft Docs](https://learn.microsoft.com/en-us/windows/wsl/install) |
| **Docker Desktop** | 4.25.0+ | Container runtime | [Docker.com](https://www.docker.com/products/docker-desktop/) |
| **Git for Windows** | 2.40+ | Version control | [Git-SCM.com](https://git-scm.com/download/win) |
| **VS Code** | Latest | Code editor (recommended) | [Code.visualstudio.com](https://code.visualstudio.com/) |
| **Node.js** | 18.x LTS | Frontend development | [Nodejs.org](https://nodejs.org/) |
| **PHP** | 8.2+ | Backend development | Included in Docker |

### Recommended VS Code Extensions

```bash
# Install VS Code extensions
code --install-extension ms-vscode-remote.remote-wsl
code --install-extension ms-azuretools.vscode-docker
code --install-extension bmewburn.vscode-intelephense-client
code --install-extension angular.ng-template
code --install-extension esbenp.prettier-vscode
code --install-extension eamodio.gitlens
```

### Verify Prerequisites

```bash
# Verify WSL2
wsl --version

# Verify Docker
docker --version
docker compose version

# Verify Git
git --version

# Verify Node.js
node --version
npm --version
```

---

## Local Development Setup

### Step 1: Clone Repository

```bash
# Open WSL2 terminal
wsl -d Ubuntu-22.04

# Navigate to your workspace
cd ~
mkdir -p projects
cd projects

# Clone repository
git clone https://github.com/your-org/TravianT4.6.git
cd TravianT4.6

# Verify clone successful
ls -la
```

**Expected Output:**
```
sections/       # PHP backend API
angularIndex/   # Angular frontend
docker/         # Docker configurations
database/       # SQL schemas
scripts/        # Utility scripts
docs/           # Documentation
```

### Step 2: Install Dependencies

#### Backend Dependencies (PHP)

```bash
# Docker Compose will handle PHP dependencies
# No manual installation needed
```

#### Frontend Dependencies (Angular)

```bash
# Navigate to frontend directory
cd angularIndex

# Install npm dependencies
npm install

# Verify installation
npm list --depth=0

# Return to root
cd ..
```

### Step 3: Configure Development Environment

```bash
# Generate development .env file
./scripts/secrets/generate-env.sh development

# This creates .env.development with development defaults
# Copy to .env for Docker Compose
cp .env.development .env

# Edit .env for local development
nano .env
```

**Development Environment Variables:**

```bash
# .env (development configuration)
DOMAIN=localhost
LETSENCRYPT_EMAIL=dev@localhost

# Database credentials (development - use simple passwords)
POSTGRES_PASSWORD=devpass123
MYSQL_ROOT_PASSWORD=devpass123
REDIS_PASSWORD=devpass123

# Grafana admin password
GRAFANA_ADMIN_PASSWORD=admin

# Development mode
APP_ENV=development
APP_DEBUG=true
LOG_LEVEL=debug

# GPU settings (set to false if no GPU available)
USE_GPU=false
OLLAMA_GPU_DEVICE=0
VLLM_GPU_DEVICE=1
```

**Note:** For development without GPU, LLMs will fall back to CPU mode (slower but functional).

### Step 4: Start Development Stack

```bash
# Start core services
docker compose up -d

# Wait for services to become healthy (1-2 minutes)
watch -n 2 'docker compose ps'

# Press Ctrl+C when all show "healthy"

# Start background workers
docker compose -f docker-compose.workers.yml up -d

# Optional: Start monitoring stack
docker compose --profile monitoring up -d
```

**Verify Services:**

```bash
# Check all services running
docker compose ps

# Expected output:
# travian_nginx        Up (healthy)
# travian_app          Up (healthy)
# travian_postgres     Up (healthy)
# travian_mysql        Up (healthy)
# travian_redis        Up (healthy)
# travian_ollama       Up
# travian_vllm         Up
```

### Step 5: Initialize Databases

```bash
# Run database initialization script
./scripts/init-databases.sh

# This creates:
# - travian_global database (PostgreSQL)
# - AI-NPC tables
# - travian_testworld database (MySQL)
# - Game tables

# Verify databases created
docker exec travian_postgres psql -U postgres -l
docker exec travian_mysql mysql -uroot -pdevpass123 -e "SHOW DATABASES;"
```

### Step 6: Verify Setup

```bash
# Run test suite
./scripts/test-stack.sh

# Expected output:
# ✅ Nginx: Healthy
# ✅ PHP-FPM: Healthy
# ✅ PostgreSQL: Healthy
# ✅ MySQL: Healthy
# ✅ Redis: Healthy
# ✅ API: Responding
# ✅ All tests passed!

# Test API endpoints
curl http://localhost/v1/health
# Expected: {"status":"ok",...}

curl http://localhost/v1/token
# Expected: {"success":true,"data":{"csrf_token":"..."}}
```

### Step 7: Start Frontend Development Server (Optional)

```bash
# Navigate to frontend
cd angularIndex

# Start Angular dev server
npm start

# Frontend available at: http://localhost:4200
# API proxied to: http://localhost/v1
```

**Your development environment is now ready! 🎉**

---

## Project Structure

### Directory Layout

```
TravianT4.6/
├── sections/                    # PHP backend code
│   ├── api/                     # REST API
│   │   ├── include/
│   │   │   ├── Controllers/     # API endpoint controllers
│   │   │   ├── Middleware/      # CSRF, logging, auth
│   │   │   ├── Models/          # Database models
│   │   │   ├── Services/        # Business logic services
│   │   │   └── Utils/           # Utility functions
│   │   └── public/
│   │       └── index.php        # API entry point
│   ├── servers/                 # Game server logic
│   └── pma/                     # phpMyAdmin
├── angularIndex/                # Angular frontend
│   ├── src/
│   │   ├── app/                 # Angular components
│   │   ├── assets/              # Images, fonts
│   │   └── environments/        # Environment configs
│   ├── angular.json             # Angular configuration
│   └── package.json             # npm dependencies
├── database/
│   ├── schemas/                 # SQL schema files
│   │   ├── postgresql/          # PostgreSQL schemas
│   │   └── mysql/               # MySQL schemas
│   └── migrations/              # Database migrations
├── docker/                      # Docker configurations
│   ├── nginx/                   # Nginx configs
│   ├── php/                     # PHP-FPM configs
│   └── workers/                 # Worker configs
├── scripts/                     # Utility scripts
│   ├── backup-postgres.sh       # Database backups
│   ├── test-stack.sh            # Integration tests
│   ├── secrets/                 # Secret management
│   └── healthcheck/             # Health check scripts
├── docs/                        # Documentation
│   ├── DEPLOYMENT-GUIDE.md
│   ├── API-REFERENCE.md
│   ├── ARCHITECTURE.md
│   └── ...
├── prometheus/                  # Monitoring config
│   └── prometheus.yml
├── grafana/                     # Grafana dashboards
│   └── dashboards/
├── docker-compose.yml           # Main services
├── docker-compose.workers.yml   # Background workers
├── .env                         # Environment variables
└── README.md                    # Project readme
```

### Key Files and Folders

**Backend (PHP):**
- `sections/api/include/Controllers/` - API endpoint controllers
- `sections/api/include/Services/` - Business logic (AI decision engine, spawn management)
- `sections/api/include/Models/` - Database models
- `sections/api/public/index.php` - API entry point, routing

**Frontend (Angular):**
- `angularIndex/src/app/` - Angular components and services
- `angularIndex/src/environments/` - Environment-specific configs

**Database:**
- `database/schemas/postgresql/` - PostgreSQL table schemas
- `database/schemas/mysql/` - MySQL game world schemas

**Infrastructure:**
- `docker-compose.yml` - Service definitions
- `docker/` - Container configurations
- `.env` - Environment variables

---

## Development Workflow

### Feature Development Process

1. **Create Feature Branch**

```bash
# Checkout main and pull latest
git checkout main
git pull origin main

# Create feature branch
git checkout -b feature/your-feature-name

# Example: git checkout -b feature/npc-combat-ai
```

2. **Develop Locally**

- Make code changes
- Test locally with Docker stack
- Write unit tests
- Update documentation

3. **Test Changes**

```bash
# Run integration tests
./scripts/test-stack.sh

# Run unit tests (if applicable)
npm test                    # Frontend tests
./vendor/bin/phpunit        # Backend tests (if configured)
```

4. **Commit Changes**

```bash
# Stage changes
git add .

# Commit with descriptive message
git commit -m "feat: add advanced combat AI for NPCs

- Implement combat strategy selection
- Add unit type effectiveness calculations
- Update decision engine to use combat AI
- Add tests for combat scenarios"

# Push to remote
git push origin feature/your-feature-name
```

5. **Create Pull Request**

- Open PR on GitHub
- Request code review
- Address review feedback
- Merge when approved

### Using Feature Flags

Feature flags allow gradual rollout and A/B testing:

```php
// Check if feature enabled
if (FeatureFlag::isEnabled('advanced_combat')) {
    // Use new combat system
    $combatAI->calculateAdvanced($attacker, $defender);
} else {
    // Use legacy combat system
    $combatAI->calculateLegacy($attacker, $defender);
}
```

**Managing Feature Flags:**

```bash
# Enable feature via API
curl -X POST http://localhost/v1/features/advanced_combat/enable \
  -H "X-CSRF-Token: $TOKEN"

# Set rollout percentage (gradual rollout)
curl -X PUT http://localhost/v1/features/advanced_combat \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{"rollout_percentage": 25}'  # 25% of users
```

### Git Workflow

**Branch Naming:**
- `feature/` - New features
- `fix/` - Bug fixes
- `docs/` - Documentation updates
- `refactor/` - Code refactoring
- `test/` - Test improvements

**Commit Message Format:**
```
<type>: <short summary>

<detailed description>

<breaking changes if any>
```

**Types:** `feat`, `fix`, `docs`, `refactor`, `test`, `chore`

---

## Running Tests

### Integration Tests

```bash
# Run full stack integration tests
./scripts/test-stack.sh

# This tests:
# - All service health
# - Database connectivity
# - API endpoints
# - Worker processes
# - GPU availability (if configured)
```

### Unit Tests (Frontend)

```bash
# Navigate to frontend
cd angularIndex

# Run tests
npm test

# Run tests with coverage
npm run test:coverage

# Watch mode for development
npm run test:watch
```

### Manual Testing

```bash
# Test specific API endpoint
curl -X POST http://localhost/v1/server/generate \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $(curl -s http://localhost/v1/token | jq -r '.data.csrf_token')" \
  -d '{
    "world_key": "test",
    "world_name": "Test World",
    "speed": 100,
    "spawn_preset_key": "npc_army_500",
    "max_npcs": 50
  }'

# Verify world created
curl http://localhost/v1/server/worlds | jq .
```

### How to Write Tests

**Example: Backend Unit Test (PHPUnit)**

```php
<?php
// tests/Services/AIDecisionEngineTest.php

use PHPUnit\Framework\TestCase;
use App\Services\AIDecisionEngine;

class AIDecisionEngineTest extends TestCase
{
    public function testNPCDecisionWithRuleBasedEngine()
    {
        $engine = new AIDecisionEngine();
        $npc = $this->createTestNPC();
        
        $decision = $engine->makeDecision($npc);
        
        $this->assertNotNull($decision);
        $this->assertArrayHasKey('action', $decision);
        $this->assertContains($decision['action'], ['build', 'train', 'attack']);
    }
}
```

**Example: Frontend Unit Test (Jasmine/Karma)**

```typescript
// angularIndex/src/app/services/api.service.spec.ts

import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { ApiService } from './api.service';

describe('ApiService', () => {
  let service: ApiService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [ApiService]
    });
    service = TestBed.inject(ApiService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  it('should fetch worlds successfully', () => {
    const mockWorlds = [
      { id: 1, world_key: 'test', world_name: 'Test World' }
    ];

    service.getWorlds().subscribe(worlds => {
      expect(worlds.length).toBe(1);
      expect(worlds[0].world_key).toBe('test');
    });

    const req = httpMock.expectOne('/v1/server/worlds');
    expect(req.request.method).toBe('GET');
    req.flush({ success: true, data: { worlds: mockWorlds } });
  });
});
```

---

## Database Development

### Schema Changes

**NEVER manually write SQL migrations!** Always use database management tools.

#### PostgreSQL Schema Changes

```bash
# Method 1: Update schema file
nano database/schemas/postgresql/ai_npc_tables.sql

# Add new column or table
# Example: ALTER TABLE ai_configs ADD COLUMN new_field VARCHAR(255);

# Apply changes
docker exec travian_postgres psql -U postgres -d travian_global -f /database/schemas/postgresql/ai_npc_tables.sql
```

#### MySQL Schema Changes

```bash
# Method 2: Use database migration tool (if configured)
# npm run db:push  # For Drizzle ORM
# php artisan migrate  # For Laravel
```

### Database Tools

**phpMyAdmin:**
- URL: http://localhost:8080
- Server: `mysql`
- Username: `root`
- Password: (from `.env` - `MYSQL_ROOT_PASSWORD`)

**PostgreSQL via psql:**

```bash
# Connect to PostgreSQL
docker exec -it travian_postgres psql -U postgres -d travian_global

# Useful commands:
\dt                          # List tables
\d+ table_name               # Describe table
SELECT * FROM players LIMIT 5;  # Query data
\q                           # Quit
```

**MySQL via mysql CLI:**

```bash
# Connect to MySQL
docker exec -it travian_mysql mysql -uroot -pdevpass123

# Useful commands:
SHOW DATABASES;
USE travian_testworld;
SHOW TABLES;
DESCRIBE players;
SELECT * FROM players LIMIT 5;
exit;
```

---

## API Development

### Creating New Endpoints

**Step 1: Create Controller**

```php
<?php
// sections/api/include/Controllers/ExampleController.php

namespace App\Controllers;

class ExampleController
{
    public function getExample($request, $response)
    {
        // Business logic
        $data = ['message' => 'Hello from Example API'];
        
        return $response->json([
            'success' => true,
            'data' => $data
        ]);
    }
    
    public function createExample($request, $response)
    {
        // Validate input
        $input = $request->getBody();
        
        // Process
        // ...
        
        return $response->json([
            'success' => true,
            'message' => 'Example created'
        ]);
    }
}
```

**Step 2: Register Routes**

```php
<?php
// sections/api/public/index.php

use App\Controllers\ExampleController;

// Add route
$router->get('/v1/example', [ExampleController::class, 'getExample']);
$router->post('/v1/example', [ExampleController::class, 'createExample']);
```

**Step 3: Test Endpoint**

```bash
# Test GET endpoint
curl http://localhost/v1/example

# Test POST endpoint
curl -X POST http://localhost/v1/example \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{"name":"test"}'
```

### Using Middleware

**CSRF Protection:**

```php
// Already applied to all POST/PUT/DELETE routes
// No action needed - middleware automatic

// In frontend, include CSRF token:
// X-CSRF-Token: <token from GET /v1/token>
```

**Logging Middleware:**

```php
// Automatically logs all requests
// Check logs at /var/log/travian/app.log
```

### Error Handling

```php
<?php
// Standard error response format

return $response->json([
    'success' => false,
    'error' => [
        'code' => 'INVALID_INPUT',
        'message' => 'World key is required',
        'details' => [
            'field' => 'world_key',
            'reason' => 'missing'
        ]
    ]
], 400);  // HTTP status code
```

### Security Best Practices

1. **Always use parameterized queries:**

```php
// Good ✅
$stmt = $db->prepare('SELECT * FROM players WHERE id = ?');
$stmt->execute([$playerId]);

// Bad ❌ - SQL injection vulnerability
$result = $db->query("SELECT * FROM players WHERE id = $playerId");
```

2. **Validate and sanitize input:**

```php
// Validate
if (!isset($input['world_key']) || empty($input['world_key'])) {
    return $response->json(['error' => 'world_key required'], 400);
}

// Sanitize
$worldKey = filter_var($input['world_key'], FILTER_SANITIZE_STRING);
```

3. **Use CSRF protection:**

```php
// Automatically handled by middleware
// Just ensure frontend sends X-CSRF-Token header
```

**See Also:** [SECURITY-HARDENING.md](SECURITY-HARDENING.md), [CORS-CSRF-SETUP.md](CORS-CSRF-SETUP.md)

---

## Frontend Development

### Angular Setup

```bash
# Navigate to frontend
cd angularIndex

# Install dependencies
npm install

# Start dev server
npm start

# Build for production
npm run build

# Lint code
npm run lint
```

### Building Frontend

```bash
# Development build (with source maps)
npm run build

# Production build (optimized)
npm run build:prod

# Output: dist/ directory
```

### API Integration

**Example: Calling API from Angular Service**

```typescript
// src/app/services/world.service.ts

import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class WorldService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getWorlds(): Observable<any> {
    return this.http.get(`${this.apiUrl}/v1/server/worlds`);
  }

  createWorld(worldData: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/v1/server/generate`, worldData);
  }
}
```

**Environment Configuration:**

```typescript
// src/environments/environment.ts (development)
export const environment = {
  production: false,
  apiUrl: 'http://localhost'
};

// src/environments/environment.prod.ts (production)
export const environment = {
  production: true,
  apiUrl: 'https://yourdomain.com'
};
```

---

## Background Workers

### Understanding Workers

TravianT4.6 uses 3 background worker types:

1. **Automation Worker** - Handles scheduled automation tasks (resource collection, auto-building)
2. **AI Decision Worker** - Processes NPC decision-making using rule-based and LLM engines
3. **Spawn Scheduler Worker** - Manages batch NPC spawning

### Testing Workers Locally

```bash
# Start workers
docker compose -f docker-compose.workers.yml up -d

# View worker logs
docker compose -f docker-compose.workers.yml logs -f

# Check worker status
docker compose -f docker-compose.workers.yml ps

# Check queue depths
docker exec travian_redis redis-cli -a devpass123 LLEN automation_queue
docker exec travian_redis redis-cli -a devpass123 LLEN ai_decision_queue
```

### Adding New Workers

**Step 1: Create Worker Script**

```php
<?php
// scripts/workers/example-worker.php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Redis;
use App\Services\Database;

$redis = new Redis();
$db = new Database();

while (true) {
    // Pop job from queue
    $job = $redis->rpop('example_queue');
    
    if ($job) {
        // Process job
        $data = json_decode($job, true);
        // ... processing logic ...
        
        echo "Processed job: " . $data['id'] . "\n";
    } else {
        // No jobs, sleep briefly
        sleep(1);
    }
}
```

**Step 2: Add to docker-compose.workers.yml**

```yaml
example-worker:
  build:
    context: ./docker/workers
  command: php /app/scripts/workers/example-worker.php
  environment:
    - DATABASE_URL=${DATABASE_URL}
    - REDIS_URL=${REDIS_URL}
  depends_on:
    - redis
    - postgres
  networks:
    - app_core
    - data_services
```

**Step 3: Deploy**

```bash
docker compose -f docker-compose.workers.yml up -d example-worker
```

---

## Debugging

### PHP Debugging

**View PHP Logs:**

```bash
# Application logs
docker compose logs php-fpm --tail=50

# Error logs
tail -50 /var/log/travian/app.log | jq .

# Filter by level
tail -100 /var/log/travian/app.log | jq 'select(.level=="error")'
```

**Enable Debug Mode:**

```bash
# Edit .env
APP_DEBUG=true
LOG_LEVEL=debug

# Restart PHP-FPM
docker compose restart php-fpm
```

**Interactive Debugging (Xdebug):**

```bash
# TODO: Configure Xdebug in docker/php/Dockerfile
# Add VS Code launch configuration for remote debugging
```

### Frontend Debugging

**Browser Developer Tools:**
- Open Chrome DevTools (F12)
- Console tab: View logs and errors
- Network tab: Inspect API requests
- Sources tab: Set breakpoints

**Angular DevTools Extension:**
```bash
# Install Chrome extension: "Angular DevTools"
# Inspect component tree and performance
```

### Database Debugging

**Query Logging (PostgreSQL):**

```bash
# Enable query logging
docker exec travian_postgres psql -U postgres -c \
  "ALTER SYSTEM SET log_statement = 'all';"

# Restart PostgreSQL
docker compose restart postgres

# View query logs
docker compose logs postgres | grep "statement:"
```

**Slow Query Analysis:**

```bash
# PostgreSQL
docker exec travian_postgres psql -U postgres -d travian_global -c \
  "SELECT query, calls, total_exec_time, mean_exec_time 
   FROM pg_stat_statements 
   ORDER BY mean_exec_time DESC LIMIT 10;"
```

### Log Analysis

**Centralized Logging (Loki):**

```bash
# Access via Grafana Explore
# http://localhost:3000/explore
# Select Loki datasource
# Query: {container_name="travian_app"}
```

---

## Code Style

### PHP Conventions

- **PSR-12** coding standard
- Classes: `PascalCase`
- Methods: `camelCase`
- Constants: `UPPER_CASE`
- Type hints for all method parameters and return types

```php
<?php

namespace App\Services;

class ExampleService
{
    private Database $db;
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    public function processData(array $input): array
    {
        // Implementation
        return ['result' => 'success'];
    }
}
```

### TypeScript Conventions

- **Angular Style Guide** conventions
- Interfaces: `PascalCase` with `I` prefix
- Components: `PascalCase` + `Component` suffix
- Services: `PascalCase` + `Service` suffix

```typescript
export interface IWorld {
  id: number;
  worldKey: string;
  worldName: string;
}

export class WorldService {
  getWorlds(): Observable<IWorld[]> {
    // Implementation
  }
}
```

### SQL Conventions

- Table names: `snake_case`, plural
- Column names: `snake_case`
- Primary keys: `id`
- Foreign keys: `<table>_id`

```sql
CREATE TABLE spawn_batches (
    id SERIAL PRIMARY KEY,
    world_id INTEGER NOT NULL,
    batch_number INTEGER NOT NULL,
    npc_count INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Documentation Standards

- Add docblocks to all classes and public methods
- Include parameter types and return types
- Provide usage examples

```php
<?php

/**
 * AI Decision Engine for NPC behavior
 * 
 * Implements 95% rule-based + 5% LLM decision strategy
 * 
 * @package App\Services
 */
class AIDecisionEngine
{
    /**
     * Make decision for NPC
     * 
     * @param NPC $npc The NPC entity
     * @return array Decision details (action, priority, etc.)
     */
    public function makeDecision(NPC $npc): array
    {
        // Implementation
    }
}
```

---

## Common Tasks

### Adding New API Endpoint

1. Create controller method
2. Register route
3. Add validation
4. Test endpoint
5. Document in API-REFERENCE.md

**See "API Development" section above**

### Adding New Database Table

1. Create schema SQL file
2. Apply schema to database
3. Create model class
4. Update documentation

```sql
-- database/schemas/postgresql/new_table.sql
CREATE TABLE new_table (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Adding New Worker

1. Create worker script
2. Add to docker-compose.workers.yml
3. Deploy worker
4. Monitor queue

**See "Background Workers" section above**

### Adding New Monitoring Metric

1. Add Prometheus metric to code
2. Update Prometheus scrape config
3. Create Grafana dashboard panel

```php
<?php
// Increment counter metric
$prometheus->increment('api_requests_total', ['endpoint' => '/v1/server/generate']);
```

---

## See Also

- [SYSTEM_DOCUMENTATION.md](SYSTEM_DOCUMENTATION.md) - AI-NPC system architecture
- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture overview
- [API-REFERENCE.md](API-REFERENCE.md) - Complete API documentation
- [DEPLOYMENT-GUIDE.md](DEPLOYMENT-GUIDE.md) - Production deployment
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Debugging common issues
- [TESTING.md](TESTING.md) - Testing strategies and procedures

---

## Getting Help

- **Documentation:** Check `docs/` directory
- **Code Examples:** Look at existing controllers and services
- **Team Chat:** Slack #travian-dev channel
- **Code Review:** Request review from senior developers
- **Troubleshooting:** See TROUBLESHOOTING.md

**Welcome to the team! Happy coding! 🚀**

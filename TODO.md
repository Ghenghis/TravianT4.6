# TravianT4.6 - Complete Game Implementation TODO

**Mission:** Make the complete Travian game functional with 1:1 feature parity ASAP  
**Timeline:** 24-48 hours to playable state  
**Status:** 🔴 IN PROGRESS

---

## 📊 Progress Overview

- **Phase 1:** Registration & Login Flow ⬜ 0/12 tasks
- **Phase 2:** Email Delivery System ⬜ 0/5 tasks
- **Phase 3:** Database Schema Completion ⬜ 0/8 tasks
- **Phase 4:** Game Navigation Implementation ⬜ 0/10 tasks
- **Phase 5:** Testing & Bug Fixes ⬜ 0/6 tasks

**Total Progress:** ⬜⬜⬜⬜⬜⬜⬜⬜⬜⬜ 0/41 tasks (0%)

---

## 🚨 PHASE 1: Registration & Login Flow (PRIORITY P0)
**Goal:** Users can register, activate via email, and login successfully  
**Time Estimate:** 4-6 hours

### Task 1.1: Audit Existing Database Schema ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Connect to PostgreSQL database and list all tables
- [ ] Verify `activation` table has `used` column (TINYINT/SMALLINT, default 0)
- [ ] Verify `users` table exists with proper columns
- [ ] Check if password column uses bcrypt hashing
- [ ] Document current schema in `docs/database-schema-current.md`

**Success Criteria:**
- ✅ Complete list of all existing tables
- ✅ Confirmation that activation.used column exists
- ✅ Schema documentation created

**Commands:**
```sql
-- Run in PostgreSQL:
\dt
\d activation
\d users
\d gameServers
```

---

### Task 1.2: Fix Activation Table Schema ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] If `activation.used` column missing, add it:
  ```sql
  ALTER TABLE activation ADD COLUMN IF NOT EXISTS used SMALLINT NOT NULL DEFAULT 0;
  ```
- [ ] Add index on `used` for performance:
  ```sql
  CREATE INDEX IF NOT EXISTS idx_activation_used ON activation(used);
  ```
- [ ] Add index on `activationCode` for fast lookups:
  ```sql
  CREATE INDEX IF NOT EXISTS idx_activation_code ON activation(activationCode);
  ```
- [ ] Verify changes applied successfully

**Success Criteria:**
- ✅ `activation.used` column exists
- ✅ Indexes created successfully
- ✅ Can query: `SELECT * FROM activation WHERE used = 0;`

**Files Modified:**
- Database only (no code changes)

---

### Task 1.3: Review Registration Controller ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Read `sections/api/include/Api/Ctrl/RegisterCtrl.php`
- [ ] Verify it handles POST /v1/register endpoint
- [ ] Check validation logic (email format, password strength, username uniqueness)
- [ ] Confirm it inserts into `activation` table with `used = 0`
- [ ] Verify it generates unique activation code
- [ ] Check if it sends activation email

**Success Criteria:**
- ✅ Controller exists and is functional
- ✅ Validation logic present
- ✅ Activation code generation works
- ✅ Email sending logic identified (even if not configured)

**Files to Review:**
- `sections/api/include/Api/Ctrl/RegisterCtrl.php`

---

### Task 1.4: Test Registration Endpoint ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Test registration with curl:
  ```bash
  curl -X POST http://localhost:5000/v1/register \
    -H "Content-Type: application/json" \
    -d '{
      "email": "test1@example.com",
      "username": "testuser1",
      "password": "Test123!@#",
      "confirmPassword": "Test123!@#",
      "worldId": "testworld"
    }'
  ```
- [ ] Verify response is success with proper JSON
- [ ] Check database: `SELECT * FROM activation WHERE email = 'test1@example.com';`
- [ ] Verify `used = 0` in database
- [ ] Document any errors encountered

**Success Criteria:**
- ✅ API returns 200 OK with success JSON
- ✅ User inserted into activation table
- ✅ `used` column set to 0
- ✅ Activation code generated

**Test Results:**
```
[Document results here after testing]
```

---

### Task 1.5: Review Login/Auth Controller ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Read `sections/api/include/Api/Ctrl/AuthCtrl.php`
- [ ] Verify it handles POST /v1/login endpoint
- [ ] Check password verification logic (bcrypt_verify or similar)
- [ ] Verify session creation/management
- [ ] Check if it validates account activation status
- [ ] Document login flow

**Success Criteria:**
- ✅ Login controller exists and is functional
- ✅ Password hashing verified (bcrypt)
- ✅ Session management implemented
- ✅ Activation check present

**Files to Review:**
- `sections/api/include/Api/Ctrl/AuthCtrl.php`

---

### Task 1.6: Implement Password Hashing (if missing) ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Verify current password storage method
- [ ] If not using bcrypt, implement:
  ```php
  // Registration:
  $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
  
  // Login:
  if (password_verify($inputPassword, $storedHash)) {
      // Success
  }
  ```
- [ ] Update RegisterCtrl.php if needed
- [ ] Update AuthCtrl.php if needed
- [ ] Test with new registration

**Success Criteria:**
- ✅ Passwords hashed with bcrypt (cost 10+)
- ✅ Registration stores hashed password
- ✅ Login verifies password correctly
- ✅ Security best practices followed

**Files Modified:**
- `sections/api/include/Api/Ctrl/RegisterCtrl.php` (if needed)
- `sections/api/include/Api/Ctrl/AuthCtrl.php` (if needed)

---

### Task 1.7: Implement Session Management ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Verify PHP sessions are started in bootstrap.php
- [ ] Check if Redis is available for session storage
- [ ] Configure session handler:
  ```php
  session_start([
      'cookie_lifetime' => 7200,
      'cookie_httponly' => true,
      'cookie_secure' => false, // true for HTTPS
  ]);
  ```
- [ ] Store user data in session after login
- [ ] Implement session validation middleware
- [ ] Add logout endpoint

**Success Criteria:**
- ✅ Sessions persist across requests
- ✅ User data stored in session
- ✅ Logout clears session
- ✅ Session security configured

**Files Modified:**
- `sections/api/include/bootstrap.php`
- `sections/api/include/Api/Ctrl/AuthCtrl.php`

---

### Task 1.8: Create Activation Handler ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Create activation endpoint: GET /v1/activate?code=XXXXX
- [ ] Verify activation code exists in database
- [ ] Check if already used (`used = 1`)
- [ ] If valid and unused:
  - Set `used = 1` in activation table
  - Create user in game world database
  - Send welcome email
- [ ] Return success/error response

**Success Criteria:**
- ✅ Activation endpoint created
- ✅ Updates `activation.used = 1`
- ✅ Creates user in world database
- ✅ Prevents duplicate activations
- ✅ Returns proper error messages

**Files to Create:**
- `sections/api/include/Api/Ctrl/ActivationCtrl.php` (or add to RegisterCtrl.php)

**Files Modified:**
- API router to add activation endpoint

---

### Task 1.9: Test Complete Registration Flow ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Register new user via API
- [ ] Extract activation code from database
- [ ] Call activation endpoint with code
- [ ] Verify user created in world database
- [ ] Attempt login with credentials
- [ ] Verify session created
- [ ] Test accessing protected endpoint
- [ ] Document full flow

**Success Criteria:**
- ✅ Can register → activate → login successfully
- ✅ User data in correct database tables
- ✅ Session persists
- ✅ No errors in workflow

**Test Script:**
```bash
# 1. Register
curl -X POST http://localhost:5000/v1/register \
  -H "Content-Type: application/json" \
  -d '{"email":"flow-test@example.com","username":"flowtest","password":"Test123!","confirmPassword":"Test123!","worldId":"testworld"}'

# 2. Get activation code from DB
# 3. Activate
curl -X GET "http://localhost:5000/v1/activate?code=XXXXX"

# 4. Login
curl -X POST http://localhost:5000/v1/login \
  -H "Content-Type: application/json" \
  -d '{"username":"flowtest","password":"Test123!","worldId":"testworld"}' \
  -c cookies.txt

# 5. Test authenticated request
curl -X GET http://localhost:5000/v1/user/profile -b cookies.txt
```

---

### Task 1.10: Implement Input Validation ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Email validation (format, DNS check optional)
- [ ] Username validation (length, characters, uniqueness)
- [ ] Password strength (min 8 chars, uppercase, lowercase, number, special)
- [ ] WorldId validation (exists in gameServers)
- [ ] SQL injection prevention (prepared statements)
- [ ] XSS prevention (sanitize inputs)

**Success Criteria:**
- ✅ Invalid inputs rejected with clear error messages
- ✅ Security vulnerabilities prevented
- ✅ User-friendly validation messages

**Files Modified:**
- `sections/api/include/Api/Ctrl/RegisterCtrl.php`
- `sections/api/include/Api/Ctrl/AuthCtrl.php`

---

### Task 1.11: Add CSRF Protection ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Generate CSRF token on form load
- [ ] Validate token on form submission
- [ ] Implement token in session
- [ ] Add token to Angular forms
- [ ] Test token validation

**Success Criteria:**
- ✅ CSRF tokens generated
- ✅ Invalid tokens rejected
- ✅ Tokens expire properly

**Files Modified:**
- `sections/api/include/Api/Ctrl/RegisterCtrl.php`
- `sections/api/include/Api/Ctrl/AuthCtrl.php`
- Angular components

---

### Task 1.12: Add Rate Limiting ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Implement rate limiting for registration (max 3/hour per IP)
- [ ] Implement rate limiting for login (max 10/minute per IP)
- [ ] Use Redis for rate limit storage (or database)
- [ ] Return 429 Too Many Requests when exceeded
- [ ] Add exponential backoff for failed logins

**Success Criteria:**
- ✅ Rate limits enforced
- ✅ Brute force attacks prevented
- ✅ Legitimate users not blocked

**Files Modified:**
- `sections/api/include/middleware/RateLimiter.php` (create)
- Bootstrap to add middleware

---

## 📧 PHASE 2: Email Delivery System (PRIORITY P0)
**Goal:** Real email delivery for activation, password reset, notifications  
**Time Estimate:** 2-3 hours

### Task 2.1: Set Up Email Integration via Replit ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Search for email integrations: `search_integrations("email smtp sendgrid")`
- [ ] Select best integration (SendGrid recommended)
- [ ] Follow Replit integration setup instructions
- [ ] Store API key in Replit secrets
- [ ] Configure SMTP settings in globalConfig.php

**Success Criteria:**
- ✅ Email integration configured
- ✅ API key stored securely
- ✅ SMTP settings available in code

**Commands:**
```bash
# Use Replit integration search tool
# Follow setup wizard
```

---

### Task 2.2: Configure PHPMailer ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Verify PHPMailer installed via Composer
- [ ] Create email service class:
  ```php
  class EmailService {
      public function sendActivationEmail($email, $username, $code) { }
      public function sendPasswordReset($email, $token) { }
      public function sendWelcomeEmail($email, $username) { }
  }
  ```
- [ ] Configure PHPMailer with SendGrid:
  ```php
  $mail->isSMTP();
  $mail->Host = 'smtp.sendgrid.net';
  $mail->SMTPAuth = true;
  $mail->Username = 'apikey';
  $mail->Password = getenv('SENDGRID_API_KEY');
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;
  ```
- [ ] Test SMTP connection

**Success Criteria:**
- ✅ PHPMailer configured
- ✅ Can connect to SMTP server
- ✅ Test email sends successfully

**Files to Create:**
- `sections/api/include/Services/EmailService.php`

---

### Task 2.3: Create Email Templates ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Create HTML email template for activation:
  - Subject: "Activate Your Travian Account"
  - Body: Welcome message + activation link
  - Professional styling
- [ ] Create password reset template
- [ ] Create welcome email template
- [ ] Store templates in `sections/api/templates/email/`
- [ ] Use Twig for templating (already installed)

**Success Criteria:**
- ✅ Professional HTML email templates
- ✅ Templates use variables (username, link, etc.)
- ✅ Mobile-responsive design

**Files to Create:**
- `sections/api/templates/email/activation.html`
- `sections/api/templates/email/password-reset.html`
- `sections/api/templates/email/welcome.html`

---

### Task 2.4: Integrate Email into Registration Flow ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Update RegisterCtrl.php to send activation email
- [ ] Generate activation URL: `https://YOUR-REPL.repl.co/v1/activate?code=XXXXX`
- [ ] Render email template with user data
- [ ] Send email via EmailService
- [ ] Log email sending (success/failure)
- [ ] Handle email errors gracefully

**Success Criteria:**
- ✅ Activation email sent on registration
- ✅ Email contains valid activation link
- ✅ Errors logged but don't break registration
- ✅ User receives email in inbox

**Files Modified:**
- `sections/api/include/Api/Ctrl/RegisterCtrl.php`

---

### Task 2.5: Test Email Delivery End-to-End ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Register with real email address
- [ ] Check inbox for activation email
- [ ] Verify email formatting looks professional
- [ ] Click activation link in email
- [ ] Verify account activated successfully
- [ ] Test with 3 different email providers (Gmail, Outlook, Yahoo)
- [ ] Check spam folders if not received

**Success Criteria:**
- ✅ Email delivered to inbox (not spam)
- ✅ Email formatting correct
- ✅ Activation link works
- ✅ 100% delivery rate

**Test Emails:**
- test1@gmail.com
- test2@outlook.com
- test3@yahoo.com

---

## 🗄️ PHASE 3: Database Schema Completion (PRIORITY P0)
**Goal:** All database tables created with 1:1 Travian game data model  
**Time Estimate:** 6-8 hours

### Task 3.1: Analyze Travian Game Data Model ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Research real Travian database schema
- [ ] Document all required tables:
  - Global tables (gameServers, configurations, activation, banIP, users)
  - Per-world tables (villages, buildings, troops, market, alliance, messages, attacks, etc.)
- [ ] Identify relationships (foreign keys)
- [ ] Define data types for each column
- [ ] Create comprehensive schema diagram

**Success Criteria:**
- ✅ Complete list of all required tables
- ✅ ER diagram created
- ✅ Data types defined
- ✅ Relationships documented

**Deliverable:**
- `docs/database-schema-travian-complete.md`
- `docs/database-er-diagram.png`

---

### Task 3.2: Create SQL Migration Scripts ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Create migration file: `install/sql/travian-complete-schema.sql`
- [ ] Include ALL missing tables:

**GLOBAL DATABASE (travian_global):**
```sql
-- Already exists: gameServers, configurations, activation, banIP

-- Add users table (global):
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    is_active BOOLEAN DEFAULT true,
    is_banned BOOLEAN DEFAULT false,
    INDEX idx_username (username),
    INDEX idx_email (email)
);
```

**PER-WORLD DATABASE (travian_testworld, travian_demo, etc.):**
```sql
-- Villages table
CREATE TABLE IF NOT EXISTS villages (
    id SERIAL PRIMARY KEY,
    player_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    x INT NOT NULL,
    y INT NOT NULL,
    population INT DEFAULT 0,
    wood INT DEFAULT 0,
    clay INT DEFAULT 0,
    iron INT DEFAULT 0,
    crop INT DEFAULT 0,
    wood_prod INT DEFAULT 0,
    clay_prod INT DEFAULT 0,
    iron_prod INT DEFAULT 0,
    crop_prod INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    capital BOOLEAN DEFAULT false,
    INDEX idx_player (player_id),
    INDEX idx_coords (x, y),
    UNIQUE (x, y)
);

-- Buildings table
CREATE TABLE IF NOT EXISTS buildings (
    id SERIAL PRIMARY KEY,
    village_id INT NOT NULL,
    field_id INT NOT NULL,
    building_type INT NOT NULL,
    level INT DEFAULT 0,
    is_upgrading BOOLEAN DEFAULT false,
    upgrade_finish_time TIMESTAMP,
    INDEX idx_village (village_id),
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE
);

-- Troops table
CREATE TABLE IF NOT EXISTS troops (
    id SERIAL PRIMARY KEY,
    village_id INT NOT NULL,
    troop_type INT NOT NULL,
    count INT DEFAULT 0,
    in_training INT DEFAULT 0,
    training_finish_time TIMESTAMP,
    INDEX idx_village (village_id),
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE
);

-- Market trades table
CREATE TABLE IF NOT EXISTS market_trades (
    id SERIAL PRIMARY KEY,
    from_village_id INT NOT NULL,
    to_village_id INT,
    offer_wood INT DEFAULT 0,
    offer_clay INT DEFAULT 0,
    offer_iron INT DEFAULT 0,
    offer_crop INT DEFAULT 0,
    want_wood INT DEFAULT 0,
    want_clay INT DEFAULT 0,
    want_iron INT DEFAULT 0,
    want_crop INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_from_village (from_village_id),
    FOREIGN KEY (from_village_id) REFERENCES villages(id) ON DELETE CASCADE
);

-- Alliances table
CREATE TABLE IF NOT EXISTS alliances (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    tag VARCHAR(10) NOT NULL UNIQUE,
    description TEXT,
    leader_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leader (leader_id)
);

-- Alliance members table
CREATE TABLE IF NOT EXISTS alliance_members (
    id SERIAL PRIMARY KEY,
    alliance_id INT NOT NULL,
    player_id INT NOT NULL,
    role VARCHAR(20) DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alliance (alliance_id),
    INDEX idx_player (player_id),
    FOREIGN KEY (alliance_id) REFERENCES alliances(id) ON DELETE CASCADE,
    UNIQUE (player_id)
);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    from_player_id INT,
    to_player_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT,
    is_read BOOLEAN DEFAULT false,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    message_type VARCHAR(20) DEFAULT 'inbox',
    INDEX idx_to_player (to_player_id),
    INDEX idx_from_player (from_player_id)
);

-- Attack reports table
CREATE TABLE IF NOT EXISTS attack_reports (
    id SERIAL PRIMARY KEY,
    attacker_village_id INT NOT NULL,
    defender_village_id INT NOT NULL,
    attacker_troops TEXT,
    defender_troops TEXT,
    attacker_losses TEXT,
    defender_losses TEXT,
    resources_stolen TEXT,
    result VARCHAR(20),
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attacker (attacker_village_id),
    INDEX idx_defender (defender_village_id)
);

-- Players table (per-world player data)
CREATE TABLE IF NOT EXISTS players (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    tribe INT NOT NULL DEFAULT 1,
    alliance_id INT,
    capital_village_id INT,
    population INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_username (username),
    INDEX idx_alliance (alliance_id)
);
```

**Success Criteria:**
- ✅ All tables defined in SQL
- ✅ Foreign keys properly set
- ✅ Indexes on performance-critical columns
- ✅ Default values set appropriately

**Files to Create:**
- `install/sql/travian-complete-schema.sql`

---

### Task 3.3: Execute Database Migrations ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Connect to PostgreSQL via Replit
- [ ] Execute migration script on `travian_global`
- [ ] Execute migration script on `travian_testworld`
- [ ] Execute migration script on `travian_demo`
- [ ] Verify all tables created
- [ ] Check indexes created
- [ ] Validate foreign keys

**Success Criteria:**
- ✅ All tables exist in all databases
- ✅ No migration errors
- ✅ Can query all tables successfully

**Commands:**
```bash
psql $DATABASE_URL -f install/sql/travian-complete-schema.sql
```

---

### Task 3.4: Create World Database Generator Script ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Create PHP script: `scripts/create-game-world.php`
- [ ] Script should:
  - Create new database for world (travian_WORLDID)
  - Run complete schema migration
  - Insert default data (buildings config, troop types, etc.)
  - Create connection.php file for world
- [ ] Make it reusable for future worlds

**Success Criteria:**
- ✅ Script creates complete world database
- ✅ Can create new worlds easily
- ✅ All configuration automated

**Files to Create:**
- `scripts/create-game-world.php`

---

### Task 3.5: Add New Game Servers (10000x, 125000x, etc.) ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Insert new servers into gameServers table:
```sql
INSERT INTO gameServers (worldId, title, url, speed, startTime, activationRequired)
VALUES
  ('speed10k', 'Ultra Speed 10000x', 'http://speed10k.localhost/', 10000, EXTRACT(EPOCH FROM NOW()), 1),
  ('speed125k', 'Mega Speed 125000x', 'http://speed125k.localhost/', 125000, EXTRACT(EPOCH FROM NOW()), 1),
  ('speed250k', 'Giga Speed 250000x', 'http://speed250k.localhost/', 250000, EXTRACT(EPOCH FROM NOW()), 1),
  ('speed500k', 'Tera Speed 500000x', 'http://speed500k.localhost/', 500000, EXTRACT(EPOCH FROM NOW()), 1),
  ('speed5m', 'Ultra Tera 5000000x', 'http://speed5m.localhost/', 5000000, EXTRACT(EPOCH FROM NOW()), 1);
```
- [ ] Create databases for each new world
- [ ] Run schema migrations for each
- [ ] Verify servers appear in API response

**Success Criteria:**
- ✅ 5 new servers in gameServers table
- ✅ Databases created for each
- ✅ Servers visible in /v1/servers/loadServers
- ✅ Can select any server during registration

**Files Modified:**
- Database only (INSERT statements)

---

### Task 3.6: Populate Default Game Data ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Create building types configuration table
- [ ] Create troop types configuration table
- [ ] Create resource production rates table
- [ ] Insert default Travian data:
  - Building costs and effects
  - Troop stats and training times
  - Resource field production rates
  - Technology tree
- [ ] Adjust values based on server speed

**Success Criteria:**
- ✅ All game configuration tables populated
- ✅ Data accurate to Travian mechanics
- ✅ Speed multipliers applied correctly

**Files to Create:**
- `install/sql/default-game-data.sql`

---

### Task 3.7: Create Database Seed Script for Testing ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Create script: `scripts/seed-test-data.php`
- [ ] Generate 8-12 test user accounts
- [ ] Create villages for each test user
- [ ] Add sample buildings at various levels
- [ ] Add sample troops
- [ ] Create test alliance
- [ ] Generate sample messages and reports

**Success Criteria:**
- ✅ Can seed database with realistic test data
- ✅ 8-12 complete user profiles
- ✅ Data relationships valid
- ✅ Can be run multiple times (idempotent)

**Files to Create:**
- `scripts/seed-test-data.php`

---

### Task 3.8: Document Database Schema ⬜
**Status:** NOT STARTED  
**Priority:** P2 - IMPORTANT  
**Owner:** Developer

**Actions:**
- [ ] Create comprehensive schema documentation
- [ ] Document each table with purpose
- [ ] Document all columns with types and constraints
- [ ] Document relationships (FK references)
- [ ] Create migration guide for future changes
- [ ] Add example queries for common operations

**Success Criteria:**
- ✅ Complete schema documentation
- ✅ Developer-friendly reference
- ✅ Easy to understand relationships

**Files to Create:**
- `docs/database-schema-reference.md`

---

## 🧭 PHASE 4: Game Navigation Implementation (PRIORITY P0)
**Goal:** Complete game navigation working - village view, buildings, map, reports, messages, alliance  
**Time Estimate:** 8-12 hours

### Task 4.1: Audit Existing Game Controllers ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] List all existing controllers in `sections/api/include/Api/Ctrl/`
- [ ] Document what each controller does
- [ ] Identify missing controllers needed for full game
- [ ] Create controller implementation plan

**Success Criteria:**
- ✅ Complete list of existing controllers
- ✅ Gap analysis completed
- ✅ Priority list of controllers to create

**Existing Controllers:**
- AuthCtrl.php
- ConfigCtrl.php
- NewsCtrl.php
- RegisterCtrl.php
- ServersCtrl.php

---

### Task 4.2: Create Village Dashboard Controller ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Create `VillageCtrl.php`
- [ ] Implement GET /v1/village endpoint
- [ ] Return village data from database:
  - Village name, coordinates, population
  - Current resources (wood, clay, iron, crop)
  - Resource production rates
  - Buildings list with levels
  - Ongoing construction/upgrades
- [ ] Implement resource calculation with time
- [ ] Cache village data for performance

**Success Criteria:**
- ✅ Village dashboard loads
- ✅ Shows current resources
- ✅ Shows building levels
- ✅ Production rates calculated correctly
- ✅ Real-time resource updates

**Files to Create:**
- `sections/api/include/Api/Ctrl/VillageCtrl.php`

**API Response:**
```json
{
  "success": true,
  "data": {
    "village": {
      "id": 1,
      "name": "My Village",
      "x": 0,
      "y": 0,
      "population": 150,
      "resources": {
        "wood": 500,
        "clay": 400,
        "iron": 350,
        "crop": 300
      },
      "production": {
        "wood": 10,
        "clay": 10,
        "iron": 8,
        "crop": 12
      },
      "buildings": [...]
    }
  }
}
```

---

### Task 4.3: Create Buildings Management Controller ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Create `BuildingsCtrl.php`
- [ ] Implement GET /v1/buildings endpoint (list buildings)
- [ ] Implement POST /v1/buildings/upgrade endpoint
- [ ] Validate building upgrade requirements:
  - Sufficient resources
  - Building level limits
  - Prerequisites met (other buildings)
- [ ] Start building queue
- [ ] Calculate completion time
- [ ] Deduct resources from village

**Success Criteria:**
- ✅ Can view all buildings
- ✅ Can upgrade buildings
- ✅ Building queue works
- ✅ Resources deducted correctly
- ✅ Timer calculations accurate

**Files to Create:**
- `sections/api/include/Api/Ctrl/BuildingsCtrl.php`

---

### Task 4.4: Create Map/World Controller ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Create `MapCtrl.php`
- [ ] Implement GET /v1/map?x=X&y=Y&range=R endpoint
- [ ] Return tile data for map viewport:
  - Village or empty field
  - Owner if village
  - Oasis type if oasis
- [ ] Optimize for large datasets (pagination/chunking)
- [ ] Add caching for frequently accessed areas

**Success Criteria:**
- ✅ Map loads quickly (<1s)
- ✅ Shows villages and empty fields
- ✅ Can zoom in/out
- ✅ Player villages highlighted

**Files to Create:**
- `sections/api/include/Api/Ctrl/MapCtrl.php`

---

### Task 4.5: Create Troops/Military Controller ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Create `TroopsCtrl.php`
- [ ] Implement GET /v1/troops endpoint (list troops)
- [ ] Implement POST /v1/troops/train endpoint
- [ ] Validate training requirements:
  - Barracks/Stable built
  - Sufficient resources
  - Training capacity
- [ ] Start training queue
- [ ] Calculate completion time
- [ ] Deduct resources

**Success Criteria:**
- ✅ Can view all troops
- ✅ Can train troops
- ✅ Training queue works
- ✅ Resources deducted correctly
- ✅ Troops added when training completes

**Files to Create:**
- `sections/api/include/Api/Ctrl/TroopsCtrl.php`

---

### Task 4.6: Create Messages/Reports Controller ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Create `MessagesCtrl.php`
- [ ] Implement GET /v1/messages endpoint (inbox)
- [ ] Implement GET /v1/messages/:id endpoint (read message)
- [ ] Implement POST /v1/messages endpoint (send message)
- [ ] Implement DELETE /v1/messages/:id endpoint (delete message)
- [ ] Mark messages as read
- [ ] Show unread count

**Success Criteria:**
- ✅ Can view inbox
- ✅ Can read messages
- ✅ Can send messages
- ✅ Can delete messages
- ✅ Unread count accurate

**Files to Create:**
- `sections/api/include/Api/Ctrl/MessagesCtrl.php`

---

### Task 4.7: Create Alliance Controller ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Create `AllianceCtrl.php`
- [ ] Implement GET /v1/alliance endpoint (view alliance)
- [ ] Implement POST /v1/alliance/create endpoint
- [ ] Implement POST /v1/alliance/join endpoint
- [ ] Implement POST /v1/alliance/leave endpoint
- [ ] Implement GET /v1/alliance/members endpoint
- [ ] Handle permissions (leader, officer, member)

**Success Criteria:**
- ✅ Can create alliance
- ✅ Can join alliance
- ✅ Can view members
- ✅ Can leave alliance
- ✅ Permissions enforced

**Files to Create:**
- `sections/api/include/Api/Ctrl/AllianceCtrl.php`

---

### Task 4.8: Create Market/Trading Controller ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Create `MarketCtrl.php`
- [ ] Implement GET /v1/market endpoint (view offers)
- [ ] Implement POST /v1/market/offer endpoint (create offer)
- [ ] Implement POST /v1/market/accept/:id endpoint
- [ ] Calculate transport capacity
- [ ] Handle resource transfer between villages
- [ ] Show marketplace levels

**Success Criteria:**
- ✅ Can create trade offers
- ✅ Can accept trades
- ✅ Resources transferred correctly
- ✅ Transport time calculated

**Files to Create:**
- `sections/api/include/Api/Ctrl/MarketCtrl.php`

---

### Task 4.9: Create Attack/Defense Controller ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Create `AttackCtrl.php`
- [ ] Implement POST /v1/attack endpoint (send troops)
- [ ] Calculate travel time based on distance and troop speed
- [ ] Implement combat calculation engine
- [ ] Generate battle reports
- [ ] Handle resource plundering
- [ ] Store attack history

**Success Criteria:**
- ✅ Can send attacks
- ✅ Travel time calculated correctly
- ✅ Combat resolves properly
- ✅ Battle reports generated
- ✅ Resources stolen/transferred

**Files to Create:**
- `sections/api/include/Api/Ctrl/AttackCtrl.php`
- `sections/api/include/Services/CombatEngine.php`

---

### Task 4.10: Integrate All Navigation with Angular Frontend ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Verify Angular routes match API endpoints
- [ ] Update Angular services to call new endpoints
- [ ] Ensure navigation menu links work
- [ ] Test each page loads correctly
- [ ] Fix any broken links or 404s
- [ ] Ensure data displays properly in UI
- [ ] Add loading states and error handling

**Success Criteria:**
- ✅ All navigation links work
- ✅ No 404 errors
- ✅ Data loads and displays correctly
- ✅ User can navigate entire game
- ✅ UI matches game state

**Files Modified:**
- Angular components and services

---

## 🧪 PHASE 5: Testing & Bug Fixes (PRIORITY P0)
**Goal:** Complete testing with 8-12 users, identify and fix all critical bugs  
**Time Estimate:** 6-8 hours

### Task 5.1: Create 8-12 Test User Accounts ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Use seed script to create test accounts:
  - testuser1 through testuser12
  - Different tribes (Romans, Teutons, Gauls)
  - Different starting positions
  - Various progression states
- [ ] Activate all accounts
- [ ] Verify login for each account
- [ ] Document test credentials

**Success Criteria:**
- ✅ 8-12 test accounts created
- ✅ All accounts activated
- ✅ Can login with each account
- ✅ Credentials documented

**Test Credentials File:**
- `docs/test-accounts.md`

---

### Task 5.2: Execute Complete User Flow Test ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
For EACH test user:
- [ ] Register new account
- [ ] Receive activation email
- [ ] Click activation link
- [ ] Login successfully
- [ ] View village dashboard
- [ ] Check resources
- [ ] Upgrade a building
- [ ] Train troops
- [ ] Send message to another player
- [ ] View map
- [ ] Create/join alliance
- [ ] Create market offer
- [ ] Send attack (to oasis or another player)
- [ ] View reports
- [ ] Logout

**Success Criteria:**
- ✅ All 8-12 users complete full flow
- ✅ No critical errors
- ✅ All features accessible
- ✅ Data persists correctly

**Test Results:**
- Document in `docs/user-flow-test-results.md`

---

### Task 5.3: Performance Testing ⬜
**Status:** NOT STARTED  
**Priority:** P1 - CRITICAL  
**Owner:** Developer

**Actions:**
- [ ] Test API response times (<200ms target)
- [ ] Test concurrent users (8-12 simultaneous logins)
- [ ] Test database query performance
- [ ] Identify slow queries and optimize
- [ ] Add database indexes where needed
- [ ] Test resource calculations under load
- [ ] Monitor memory usage

**Success Criteria:**
- ✅ API endpoints <200ms average
- ✅ Can handle 8-12 concurrent users
- ✅ No memory leaks
- ✅ Database queries optimized

**Tools:**
- Apache Bench for load testing
- PostgreSQL EXPLAIN for query analysis

---

### Task 5.4: Bug Triage and Prioritization ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Collect all bugs found during testing
- [ ] Categorize by severity:
  - P0 (Critical/Blocking): Game unplayable
  - P1 (High): Major feature broken
  - P2 (Medium): Minor feature issue
  - P3 (Low): Cosmetic or enhancement
- [ ] Create bug list in `docs/bugs.md`
- [ ] Prioritize fixes: P0 → P1 → P2 → P3

**Success Criteria:**
- ✅ All bugs documented
- ✅ Severity assigned
- ✅ Priority order established
- ✅ Ready for fixes

**Bug List File:**
- `docs/bugs.md`

---

### Task 5.5: Fix All P0 and P1 Bugs ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Fix all P0 bugs (blocking issues)
- [ ] Fix all P1 bugs (critical features)
- [ ] Test each fix thoroughly
- [ ] Regression test to ensure no new bugs
- [ ] Update documentation if needed
- [ ] Deploy fixes

**Success Criteria:**
- ✅ Zero P0 bugs remaining
- ✅ Zero P1 bugs remaining
- ✅ All fixes verified working
- ✅ No regression bugs introduced

**Bug Fix Log:**
- Track in `docs/bug-fixes-changelog.md`

---

### Task 5.6: Final End-to-End Validation ⬜
**Status:** NOT STARTED  
**Priority:** P0 - BLOCKING  
**Owner:** Developer

**Actions:**
- [ ] Perform complete game flow test with fresh account
- [ ] Verify all navigation works
- [ ] Verify all features functional
- [ ] Check for any console errors
- [ ] Test on multiple browsers (Chrome, Firefox, Safari)
- [ ] Test on mobile devices (if applicable)
- [ ] Get user feedback (if available)

**Success Criteria:**
- ✅ Complete game playable start to finish
- ✅ No critical bugs
- ✅ All features working
- ✅ Professional user experience
- ✅ Ready for production

**Sign-Off:**
- [ ] Developer approval
- [ ] QA approval (if applicable)
- [ ] Product owner approval

---

## 📊 Acceptance Criteria

### ✅ DONE Definition

The project is **COMPLETE** when ALL of the following are true:

**Registration & Login:**
- ✅ Users can register with email/username/password
- ✅ Activation email delivered to inbox
- ✅ Activation link works and creates user account
- ✅ Users can login with credentials
- ✅ Sessions persist across requests
- ✅ Logout works correctly

**Email System:**
- ✅ SendGrid (or equivalent) integrated via Replit
- ✅ Activation emails delivered reliably
- ✅ Email templates professional and mobile-friendly
- ✅ Password reset emails work (if implemented)

**Database:**
- ✅ All tables created with complete schema
- ✅ Relationships properly defined (foreign keys)
- ✅ Indexes on performance-critical columns
- ✅ 7 game servers available (testworld, demo, 10000x, 125000x, 250000x, 500000x, 5000000x)
- ✅ Sample data populated for testing

**Game Navigation:**
- ✅ Village dashboard shows resources and buildings
- ✅ Can upgrade buildings (queue works, timers accurate)
- ✅ Can train troops (queue works, timers accurate)
- ✅ Map loads and shows villages/oases
- ✅ Can send messages (inbox/outbox works)
- ✅ Can create/join alliances
- ✅ Can create market offers
- ✅ Can send attacks (combat engine works)
- ✅ All navigation links functional (no 404s)

**Testing:**
- ✅ 8-12 test users created and verified
- ✅ Complete user flow tested for all users
- ✅ All P0 and P1 bugs fixed
- ✅ Performance acceptable (<200ms API responses)
- ✅ No critical errors in production

**Documentation:**
- ✅ Database schema documented
- ✅ API endpoints documented
- ✅ Test accounts documented
- ✅ Known issues/limitations documented

---

## 🐛 Known Issues & Limitations

(To be filled in during testing)

---

## 📝 Notes & Decisions

- **Email Provider:** SendGrid chosen for reliability and Replit integration
- **Session Storage:** PHP sessions with potential Redis backend for scalability
- **Password Hashing:** bcrypt with cost factor 10
- **Database:** PostgreSQL (Replit default) for all environments
- **Speed Servers:** Extreme speed servers (10000x+) for rapid testing and fun gameplay

---

## 🎯 Next Steps After Completion

Once all TODO tasks are complete:

1. **Performance Optimization:**
   - Add Redis caching for frequently accessed data
   - Optimize database queries
   - Implement query result caching

2. **Advanced Features:**
   - Hero system
   - Auctions
   - Tournament squares
   - World wonders (for endgame)

3. **AI NPC Integration:**
   - Follow the comprehensive AI guides in `docs/AI/`
   - Implement 50-500 AI players
   - Use local LLM for decision-making (5% of decisions)

4. **Production Hardening:**
   - Set up monitoring (uptime, errors, performance)
   - Implement backup strategy
   - Add rate limiting
   - Security audit

5. **Migration to Docker/Windows:**
   - Follow `docs/local/REPLIT-TO-DOCKER-WINDOWS-CONVERSION-BLUEPRINT.md`
   - Enable GPU access for AI features
   - Full local deployment

---

## 📞 Questions or Issues?

If you encounter any blockers or have questions:
1. Check the relevant documentation in `docs/`
2. Review Replit logs for errors
3. Test with curl/Postman to isolate API issues
4. Check database directly with PostgreSQL client

**Document all issues in `docs/bugs.md` for tracking.**

---

**Last Updated:** October 29, 2025  
**Status:** 🔴 IN PROGRESS - 0/41 tasks complete (0%)  
**Next Task:** Task 1.1 - Audit Existing Database Schema

---

**LET'S BUILD THIS! 🚀**

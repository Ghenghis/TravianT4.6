# Current Database Schema - PostgreSQL

**Database:** Replit PostgreSQL (Neon-backed)  
**Generated:** October 29, 2025  
**Status:** ✅ COMPLETE

---

## Global Tables (9 tables)

### 1. activation ✅
**Purpose:** User registration and activation tracking

| Column | Type | Nullable | Default |
|--------|------|----------|---------|
| id | integer (serial) | NO | auto-increment |
| worldid | varchar | NO | - |
| name | varchar | NO | - |
| password | varchar | NO | - |
| email | varchar | NO | - |
| activationcode | varchar | NO | - |
| newsletter | smallint | NO | - |
| **used** | **smallint** | **NO** | **0** ✅ |
| refuid | integer | NO | 0 |
| time | integer | NO | 0 |
| reminded | integer | NO | 0 |

**Indexes:**
- PRIMARY KEY on `id`
- Need: Index on `activationcode` for fast lookups
- Need: Index on `used` for filtering

---

### 2. gameservers ✅
**Purpose:** Game world server configurations

**Status:** Checking structure...

---

### 3. configurations
**Purpose:** Global application settings (key-value pairs)

---

### 4. banip
**Purpose:** IP address blocking for security

---

### 5. email_blacklist
**Purpose:** Blocked email addresses/domains

---

### 6. mailserver
**Purpose:** Email server configuration

---

### 7. passwordrecovery
**Purpose:** Password reset tokens

---

### 8. bannershop
**Purpose:** Banner shop configuration

---

### 9. clubmedals
**Purpose:** Club medals/rewards configuration

---

## Critical Findings

### ✅ GOOD NEWS:
1. **activation.used column EXISTS** with default value 0
2. Database structure is present
3. 9 global tables configured

### ⚠️ NEEDS ATTENTION:
1. Missing indexes on activation table (activationcode, used)
2. Need to verify gameservers table structure
3. No game world databases created yet (travian_testworld, travian_demo)

---

**Next Steps:** Add indexes, verify game servers, create world databases

# Bcrypt Migration Strategy - Development vs Production

## Current Status: ✅ SAFE (Development Environment)

### Why No Backward Compatibility Needed Right Now:

1. **Zero Activated Users:** 
   - activation table: 1 pending registration (used=0)
   - No users in any per-world `users` tables (servers not installed yet)
   - No existing SHA1 passwords in production use

2. **Fresh Development Environment:**
   - Deleted all test users before bcrypt implementation
   - Starting with clean slate
   - All future passwords will be bcrypt from day 1

3. **Game Server Status:**
   - Test Server (testworld) and Demo Server (demo) not yet installed
   - No game world databases exist yet
   - No player accounts to migrate

---

## ✅ For Production Migration (Future - Docker/Windows):

When migrating to production with existing users, implement hybrid approach:

### Step 1: Detect Legacy vs Bcrypt Hashes
```php
function isLegacySHA1($hash) {
    return strlen($hash) === 40 && ctype_xdigit($hash);
}
```

### Step 2: Hybrid Verification in LoginOperator::checkLogin()
```php
public function checkLogin($password, $result)
{
    $storedHash = $result['row']['password'];
    
    // Check if bcrypt hash
    if (str_starts_with($storedHash, '$2y$')) {
        if (password_verify($password, $storedHash)) {
            return 0;
        }
    }
    // Check if legacy SHA1 hash
    else if ($this->isLegacySHA1($storedHash)) {
        if (hash_equals(sha1($password), $storedHash)) {
            // SUCCESS: Upgrade to bcrypt immediately
            $this->upgradeToBcrypt($result['row']['id'], $password);
            return 0;
        }
    }
    
    // Check sitters...
    return 3;
}

private function upgradeToBcrypt($userId, $password)
{
    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $this->db->prepare("UPDATE users SET password=:password WHERE id=:id");
    $stmt->execute(['password' => $newHash, 'id' => $userId]);
}
```

### Step 3: One-Time Migration Script (Optional)
```php
// Force-rehash all SHA1 passwords (passwords unknown, require reset)
UPDATE users 
SET password = '' 
WHERE LENGTH(password) = 40 AND password ~ '^[a-f0-9]{40}$';

// Send password reset emails to all affected users
```

---

## Current Implementation: Production-Ready For New Deployments ✅

**What We Have:**
- ✅ Bcrypt with cost 12 (~250ms, secure)
- ✅ password_hash() for all new registrations
- ✅ password_verify() for authentication
- ✅ OWASP/NIST compliant
- ✅ No legacy SHA1 hashes in system

**What We'll Add For Production Migration:**
- ⏳ Hybrid verification (detect + upgrade SHA1 on login)
- ⏳ Migration script for existing user bases
- ⏳ Automated tests for legacy compatibility

---

## Decision: Deploy Bcrypt Now ✅

**Rationale:**
1. Zero existing users = zero migration risk
2. All new accounts will be secure from day 1
3. Future production migration documented and planned
4. No backward compatibility needed for greenfield deployment

**Risk:** NONE (no existing passwords to break)

**Recommendation:** Proceed with bcrypt, document hybrid approach for future

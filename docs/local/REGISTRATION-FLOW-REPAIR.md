# Registration Flow Repair

## 🎯 Purpose

Fix the broken registration system to enable user account creation.

**Estimated Time:** 2-3 hours

---

## Critical Fixes Needed

### Fix 1: Activation Table Schema

Ensure `activation.used` column exists (see Guide 5):

```sql
ALTER TABLE activation ADD COLUMN IF NOT EXISTS used TINYINT NOT NULL DEFAULT 0;
```

### Fix 2: Update Registration Handler

File: `sections/api/include/Api/Controllers/ActivateHandler.php`

Ensure it sets `used = 1` after activation:

```php
$stmt = $conn->prepare("UPDATE activation SET used = 1 WHERE code = ?");
```

### Fix 3: Test Registration Flow

```bash
curl -X POST http://localhost:5000/v1/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","username":"testuser","password":"Test123!","worldId":"testworld"}'
```

**Expected:** Success response with activation instructions

---

## ✅ Verification

- [ ] Can access registration page
- [ ] Can submit registration form without errors
- [ ] User inserted into `activation` table
- [ ] Activation email sent (or queued)

---

**Next guide:** [EMAIL-DELIVERY.md](./EMAIL-DELIVERY.md)

---

**Last Updated:** October 29, 2025

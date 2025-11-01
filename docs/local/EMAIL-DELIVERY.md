# Email Delivery Setup

## 🎯 Purpose

Configure email delivery for activation links and notifications.

**Estimated Time:** 1-2 hours

---

## Development Setup (MailHog)

### Step 1: Run MailHog Container

```bash
docker run -d \
  --name travian-mailhog \
  --network travian-network \
  -p 1025:1025 \
  -p 8025:8025 \
  --restart unless-stopped \
  mailhog/mailhog
```

### Step 2: Configure SMTP

Update `.env`:
```env
SMTP_HOST=mailhog
SMTP_PORT=1025
SMTP_FROM=noreply@localhost
```

### Step 3: View Emails

Open: http://localhost:8025

All test emails appear in MailHog UI.

---

## Production Setup (SendGrid)

### Step 1: Get SendGrid API Key

1. Sign up at https://sendgrid.com
2. Create API Key with "Mail Send" permission
3. Copy the key

### Step 2: Configure

Update `.env`:
```env
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USER=apikey
SMTP_PASSWORD=SG.your-api-key-here
SMTP_FROM=noreply@yourdomain.com
```

---

## ✅ Verification

Test email sending:

```bash
php scripts/test-email.php test@example.com
```

**Expected:** Email appears in MailHog (dev) or inbox (production)

---

**Next guide:** [LOGIN-SESSION-STABILITY.md](./LOGIN-SESSION-STABILITY.md)

---

**Last Updated:** October 29, 2025

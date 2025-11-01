# CORS & CSRF Security Configuration

## Overview

This project implements comprehensive CORS (Cross-Origin Resource Sharing) and CSRF (Cross-Site Request Forgery) protection.

## CORS Configuration

### Allowed Origins

**Development:**
- http://localhost:4200 (Angular dev server)
- http://localhost:5000 (Direct nginx access)
- http://localhost:3000 (Alternative frontend)

**Production:**
- https://{DOMAIN}
- https://www.{DOMAIN}
- https://*.{DOMAIN} (all subdomains)

### Implementation Layers

1. **Nginx (Primary):**
   - Sets CORS headers for all responses
   - Handles preflight OPTIONS requests
   - Origin whitelist via map directive

2. **PHP (Fallback):**
   - Validates origin if nginx headers missing
   - Returns 403 for unauthorized origins
   - Fail-closed approach

### Credentials Support

CORS requests with credentials (cookies, auth headers) are supported:
```
Access-Control-Allow-Credentials: true
```

## CSRF Protection

### Double-Submit Cookie Pattern

API requests use double-submit cookie pattern:
1. Client requests CSRF token: GET /v1/token
2. Server sets XSRF-TOKEN cookie and returns token
3. Client includes token in X-CSRF-Token header
4. Server validates cookie matches header

### Protected Methods

CSRF protection applies to:
- POST
- PUT
- PATCH
- DELETE

GET, HEAD, OPTIONS exempt (safe methods).

### Exempt Paths

These paths don't require CSRF tokens:
- /v1/health (health checks)
- /v1/servers/list (public read-only)

Add exemptions in CSRFMiddleware constructor.

### Token Validation

```php
// Get CSRF token
$token = CSRFTokenManager::generateToken();

// Validate CSRF token
if (!CSRFMiddleware::validateToken()) {
    // Request rejected
}
```

### Failed Validation Logging

CSRF failures logged to `/var/log/travian/csrf-failures.log`:
```json
{
  "timestamp": "2025-10-30 12:00:00",
  "ip": "192.168.1.100",
  "method": "POST",
  "uri": "/v1/auth/login",
  "origin": "https://evil.com",
  "referer": "https://evil.com/attack",
  "user_agent": "Mozilla/5.0..."
}
```

Monitor these logs with ModSecurity for correlation.

## Cookie Security Policies

### Session Cookie

```
Name: TRAVIAN_SESSION
Lifetime: Session (expires on browser close)
Secure: true (production only)
HttpOnly: true (prevent JavaScript access)
SameSite: Lax (CSRF protection)
```

### CSRF Token Cookie

```
Name: XSRF-TOKEN
Lifetime: Session
Secure: true (production) / false (dev)
HttpOnly: false (JavaScript needs access)
SameSite: None+Secure (production) / Lax (dev)
```

## Frontend Integration

### Angular HTTP Interceptor

```typescript
import { HttpInterceptor, HttpRequest, HttpHandler } from '@angular/common/http';

export class CsrfInterceptor implements HttpInterceptor {
  intercept(req: HttpRequest<any>, next: HttpHandler) {
    // Get CSRF token from cookie
    const csrfToken = this.getCookie('XSRF-TOKEN');
    
    if (csrfToken) {
      // Clone request and add X-CSRF-Token header
      req = req.clone({
        setHeaders: {
          'X-CSRF-Token': csrfToken
        },
        withCredentials: true  // Send cookies
      });
    }
    
    return next.handle(req);
  }
  
  private getCookie(name: string): string | null {
    const matches = document.cookie.match(
      new RegExp(`(?:^|; )${name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1')}=([^;]*)`)
    );
    return matches ? decodeURIComponent(matches[1]) : null;
  }
}
```

### Initial Token Fetch

```typescript
// On app initialization
this.http.get('/v1/token').subscribe(response => {
  // Token set in XSRF-TOKEN cookie automatically
  // response.data.token contains the token value
});
```

## Testing

### CORS Testing

```bash
# Test valid origin
curl -H "Origin: http://localhost:4200" \
     -H "Access-Control-Request-Method: POST" \
     -X OPTIONS \
     http://localhost:5000/v1/auth/login

# Should return 204 with CORS headers

# Test invalid origin
curl -H "Origin: https://evil.com" \
     -H "Access-Control-Request-Method: POST" \
     -X OPTIONS \
     http://localhost:5000/v1/auth/login

# Should return 403
```

### CSRF Testing

```bash
# Get CSRF token
TOKEN=$(curl -s -c cookies.txt http://localhost:5000/v1/token | jq -r '.data.token')

# Make POST request with token
curl -b cookies.txt \
     -H "X-CSRF-Token: $TOKEN" \
     -H "Content-Type: application/json" \
     -X POST \
     -d '{"username":"test","password":"test"}' \
     http://localhost:5000/v1/auth/login

# Should succeed

# Make POST without token
curl -H "Content-Type: application/json" \
     -X POST \
     -d '{"username":"test","password":"test"}' \
     http://localhost:5000/v1/auth/login

# Should return 403 CSRF validation failed
```

## Troubleshooting

### CORS Errors in Browser

Check:
1. Origin in whitelist (dev-http.conf or prod-https.conf.template)
2. Nginx CORS headers present (browser DevTools Network tab)
3. Credentials flag: `withCredentials: true` in fetch/XHR

### CSRF Validation Failures

Check:
1. CSRF token cookie set (browser DevTools Application tab)
2. X-CSRF-Token header present (browser DevTools Network tab)
3. Token matches cookie value
4. Method is POST/PUT/PATCH/DELETE

### Production Issues

Check:
1. DOMAIN environment variable set correctly
2. Cookies use Secure flag (HTTPS only)
3. SameSite=None requires Secure flag

## Security Best Practices

1. **Never disable CORS** - Use proper whitelisting
2. **Always validate CSRF** - No shortcuts
3. **Log failures** - Monitor for attacks
4. **Rotate tokens** - After login/logout
5. **Use HTTPS** - Production only
6. **Review logs** - Check /var/log/travian/csrf-failures.log regularly

#!/bin/sh
set -e

echo "🔒 Starting Let's Encrypt certificate renewal check..."

if [ -z "$DOMAIN" ]; then
    echo "❌ ERROR: DOMAIN environment variable not set"
    exit 1
fi

if [ -z "$LETSENCRYPT_EMAIL" ]; then
    echo "❌ ERROR: LETSENCRYPT_EMAIL environment variable not set"
    exit 1
fi

if [ ! -d "/etc/letsencrypt/live/$DOMAIN" ]; then
    echo "📝 Obtaining new certificate for $DOMAIN..."
    certbot certonly --webroot -w /var/www/certbot \
        --email "$LETSENCRYPT_EMAIL" \
        --agree-tos \
        --no-eff-email \
        --non-interactive \
        -d "$DOMAIN" \
        --key-type rsa \
        --rsa-key-size 4096
else
    echo "🔄 Renewing certificate for $DOMAIN..."
    certbot renew --webroot -w /var/www/certbot --non-interactive
fi

if [ $? -eq 0 ]; then
    echo "✅ Certificate operation successful"
    echo "🔄 Reloading nginx..."
    
    NGINX_CONTAINER=$(docker compose ps -q nginx 2>/dev/null)
    
    if [ -n "$NGINX_CONTAINER" ]; then
        docker exec "$NGINX_CONTAINER" nginx -s reload 2>/dev/null && echo "✅ Nginx reloaded successfully"
    else
        echo "⚠️  Could not find nginx container (may not be running)"
    fi
else
    echo "❌ Certificate operation failed"
    exit 1
fi

echo "✅ Certificate renewal check complete"

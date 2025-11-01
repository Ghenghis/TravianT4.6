#!/bin/sh
set -e

if [ -f /etc/nginx/conf.d/prod-https.conf.template ]; then
    echo "Templating prod-https.conf with DOMAIN=${DOMAIN:-localhost}..."
    envsubst '${DOMAIN}' < /etc/nginx/conf.d/prod-https.conf.template > /etc/nginx/conf.d/prod-https.conf
fi

nginx -t

exec "$@"

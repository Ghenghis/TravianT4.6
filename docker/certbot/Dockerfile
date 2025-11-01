FROM certbot/certbot:latest

RUN apk add --no-cache docker-cli

COPY docker/certbot/renew-certs.sh /renew-certs.sh
RUN chmod +x /renew-certs.sh

CMD ["/renew-certs.sh"]

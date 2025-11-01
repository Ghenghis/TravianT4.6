FROM alpine:latest

# Install required packages
RUN apk add --no-cache \
    bash \
    postgresql-client \
    mysql-client \
    gzip \
    findutils \
    coreutils \
    dcron \
    tzdata

# Copy backup scripts
COPY scripts/backup-postgres.sh /usr/local/bin/
COPY scripts/backup-mysql.sh /usr/local/bin/
COPY scripts/restore-postgres.sh /usr/local/bin/
COPY scripts/restore-mysql.sh /usr/local/bin/

# Make scripts executable
RUN chmod +x /usr/local/bin/backup-postgres.sh \
    && chmod +x /usr/local/bin/backup-mysql.sh \
    && chmod +x /usr/local/bin/restore-postgres.sh \
    && chmod +x /usr/local/bin/restore-mysql.sh

# Setup cron jobs
RUN echo "# PostgreSQL backup - Daily at 2 AM" >> /etc/crontabs/root && \
    echo "0 2 * * * /usr/local/bin/backup-postgres.sh >> /var/log/backup-postgres.log 2>&1" >> /etc/crontabs/root && \
    echo "# MySQL backup - Daily at 3 AM" >> /etc/crontabs/root && \
    echo "0 3 * * * /usr/local/bin/backup-mysql.sh >> /var/log/backup-mysql.log 2>&1" >> /etc/crontabs/root

# Create log directory
RUN mkdir -p /var/log && touch /var/log/backup-postgres.log /var/log/backup-mysql.log

# Start cron in foreground
CMD ["crond", "-f", "-l", "2"]

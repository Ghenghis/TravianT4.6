FROM mysql:8.0

ENV MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
ENV MYSQL_DATABASE=travian_global
ENV MYSQL_USER=${MYSQL_USER}
ENV MYSQL_PASSWORD=${MYSQL_PASSWORD}

COPY my.cnf /etc/mysql/conf.d/custom.cnf
COPY init/ /docker-entrypoint-initdb.d/

RUN chmod -R 755 /docker-entrypoint-initdb.d/

EXPOSE 3306

CMD ["mysqld"]

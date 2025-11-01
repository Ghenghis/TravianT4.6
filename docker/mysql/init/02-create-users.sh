#!/bin/bash
set -e

mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
	CREATE USER IF NOT EXISTS 'travian_user'@'%' IDENTIFIED BY '$MYSQL_PASSWORD';
	
	GRANT ALL PRIVILEGES ON travian_global.* TO 'travian_user'@'%';
	GRANT ALL PRIVILEGES ON travian_testworld.* TO 'travian_user'@'%';
	GRANT ALL PRIVILEGES ON travian_demo.* TO 'travian_user'@'%';
	
	GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER, LOCK TABLES, REFERENCES ON travian_global.* TO 'travian_user'@'%';
	GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER, LOCK TABLES, REFERENCES ON travian_testworld.* TO 'travian_user'@'%';
	GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER, LOCK TABLES, REFERENCES ON travian_demo.* TO 'travian_user'@'%';
	
	FLUSH PRIVILEGES;
EOSQL

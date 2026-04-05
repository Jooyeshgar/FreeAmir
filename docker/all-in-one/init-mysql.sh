#!/bin/bash

set -e

echo “🔧 Initializing MySQL database system…”

mysql_install_db --user=mysql --datadir=/var/lib/mysql

# Start MySQL temporarily
mysqld_safe --datadir=/var/lib/mysql --skip-networking &

MYSQL_PID=$!

echo "⏳ Waiting for MySQL to start…"

for i in {30..0}; do
    if mysqladmin ping -h localhost --silent; then
        break
    fi
    sleep 1
done

if [ “$i” = 0 ]; then

    echo “❌ MySQL failed to start”
    exit 1

fi

echo “✅ MySQL started, creating database and user…”

mysql -u root <<-EOSQL

    CREATE DATABASE IF NOT EXISTS freeamir CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS ‘freeamir’@‘localhost’ IDENTIFIED BY ‘freeamir’;
    CREATE USER IF NOT EXISTS ‘freeamir’@‘%’ IDENTIFIED BY ‘freeamir’;
    GRANT ALL PRIVILEGES ON freeamir.* TO ‘freeamir’@‘localhost’;
    GRANT ALL PRIVILEGES ON freeamir.* TO ‘freeamir’@‘%’;
    FLUSH PRIVILEGES;

EOSQL

echo “✅ Database and user created”
mysqladmin -u root shutdown
wait $MYSQL_PID
echo “✅ MySQL initialization complete”
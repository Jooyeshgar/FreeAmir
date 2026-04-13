#!/bin/bash

set -e

echo "Initializing MariaDB database system..."

echo "MariaDB started, creating database and user..."

mariadb -u root <<-EOSQL
    CREATE DATABASE IF NOT EXISTS freeamir CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'freeamir'@'localhost' IDENTIFIED BY 'freeamir';
    CREATE USER IF NOT EXISTS 'freeamir'@'%' IDENTIFIED BY 'freeamir';
    GRANT ALL PRIVILEGES ON freeamir.* TO 'freeamir'@'localhost';
    GRANT ALL PRIVILEGES ON freeamir.* TO 'freeamir'@'%';
    FLUSH PRIVILEGES;
EOSQL

echo "Database and user created"


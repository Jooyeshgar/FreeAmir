#!/bin/bash

../vendor/bin/sail artisan migrate:fresh --seed
(echo "SET FOREIGN_KEY_CHECKS=0;" && cat 1400.sql) | mysql -h 127.0.0.1 -u root -ppassword freeamir
mysql -h 127.0.0.1 -u root -ppassword freeamir < oldSchemaToNew.sql
../vendor/bin/sail artisan fiscal-year:export 1 --output exports/1400.json

../vendor/bin/sail artisan migrate:fresh --seed
(echo "SET FOREIGN_KEY_CHECKS=0;" && cat 1401.sql) | mysql -h 127.0.0.1 -u root -ppassword freeamir
mysql -h 127.0.0.1 -u root -ppassword freeamir < oldSchemaToNew.sql
../vendor/bin/sail artisan fiscal-year:export 1 --output exports/1401.json

../vendor/bin/sail artisan migrate:fresh --seed
(echo "SET FOREIGN_KEY_CHECKS=0;" && cat 1402.sql) | mysql -h 127.0.0.1 -u root -ppassword freeamir
mysql -h 127.0.0.1 -u root -ppassword freeamir < oldSchemaToNew.sql
../vendor/bin/sail artisan fiscal-year:export 1 --output exports/1402.json

../vendor/bin/sail artisan migrate:fresh --seed
(echo "SET FOREIGN_KEY_CHECKS=0;" && cat 1403.sql) | mysql -h 127.0.0.1 -u root -ppassword freeamir
mysql -h 127.0.0.1 -u root -ppassword freeamir < oldSchemaToNew.sql
../vendor/bin/sail artisan fiscal-year:export 1 --output exports/1403.json

../vendor/bin/sail artisan migrate:fresh --seed
(echo "SET FOREIGN_KEY_CHECKS=0;" && cat 1404.sql) | mysql -h 127.0.0.1 -u root -ppassword freeamir
mysql -h 127.0.0.1 -u root -ppassword freeamir < oldSchemaToNew.sql
../vendor/bin/sail artisan fiscal-year:export 1 --output exports/1404.json

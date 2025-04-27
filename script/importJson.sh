#!/bin/bash

../vendor/bin/sail artisan migrate:fresh --seed

../vendor/bin/sail artisan fiscal-year:import exports/1400.json 1400 --name="جویشگر ۱۴۰۰" --force
../vendor/bin/sail artisan fiscal-year:import exports/1401.json 1401 --name="جویشگر ۱۴۰۱" --force
../vendor/bin/sail artisan fiscal-year:import exports/1402.json 1402 --name="جویشگر ۱۴۰۲" --force
../vendor/bin/sail artisan fiscal-year:import exports/1403.json 1403 --name="جویشگر ۱۴۰۳" --force

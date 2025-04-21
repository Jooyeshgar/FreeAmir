# Amir Accounting Software - Database Migration Guide

This guide explains how to convert an [old SQLite database format](https://github.com/Jooyeshgar/amir) to a new MySQL database for the Amir Accounting Software built with Laravel.

## Important: Backup Your Data
Before proceeding, **back up all your accounting data**. This migration process involves multiple steps, and there is a risk of data loss if not performed correctly. Ensure you have a complete backup of your SQLite database file and any other critical data.

## Migration Steps

### 1. Convert SQLite to SQL Format
Run the provided Python script to convert your SQLite database to an SQL file compatible with MySQL.

Execute the following command in your terminal:
```bash
cd script
python3 sqliteToMysql.py old_amir_db.sqlite
```

This will generate an SQL file from the `old_amir_db.sqlite` database.

### 2. Set Up the New MySQL Database
Ensure you have a clean MySQL database ready for the Laravel project.

1. Run Laravel's migration and seeding commands to create the new database schema:
   ```bash
   sail artisan migrate --seed
   ```
   This will create the new tables.

2. Import the SQL file generated in Step 1 into the clean MySQL database. You can use a tool like MySQL Workbench, phpMyAdmin, or the MySQL command line:
   ```bash
   mysql -u your_username -p your_database_name < old_amir_db.sql
   ```
   Replace `your_username`, `your_database_name`, and `old_amir_db.sql` with the appropriate values.
   
   This will create some tables with an `_old` suffix to store the imported data temporarily.

### 3. Convert Old Schema to New Schema
Run the SQL script to transform the old schema (tables with `_old` suffix) into the new schema and remove the temporary `_old` tables.

Execute the following SQL script:
```bash
mysql -u your_username -p your_database_name < oldSchemaToNew.sql
```
Alternatively, you can run it through your database management tool.

This script will:
- Transform data from the old schema to the new schema.
- Drop the temporary `_old` tables once the conversion is complete.

## Troubleshooting
If you encounter errors during the migration process:
- Check that all prerequisites are installed (Python 3, MySQL client)
- Verify database user permissions
- Ensure all file paths are correct
- Review error messages for specific issues

## Notes
- Ensure all commands are executed in the correct order to avoid data corruption.
- Verify the data integrity after each step, especially after importing the SQL file and running the conversion script.
- If you encounter issues, restore your backup and review the steps or contact support.

By following these steps, your Amir Accounting Software database should be successfully migrated from SQLite to MySQL with the new Laravel schema.
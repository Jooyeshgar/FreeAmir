# Amir Accounting Software - Database Migration Guide

This guide explains how to convert an [old SQLite database format](https://github.com/Jooyeshgar/amir) to a new MySQL database for the Amir Accounting Software built with Laravel.

## Important: Back Up Your Data
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
   sail artisan migrate:fresh --seed
   ```
   This will create the new tables.

2. Import the SQL file generated in Step 1 into the clean MySQL database. You can use a tool like MySQL Workbench, phpMyAdmin, or the MySQL command line:
   ```bash
   (echo "SET FOREIGN_KEY_CHECKS=0;" && cat script/old_amir_db.sql) | mysql -h 127.0.0.1 -u root -p freeamir
   ```
   Replace `root`, `freeamir` (database name), `127.0.0.1`, and `old_amir_db.sql` with the appropriate values.
   
   This will create some tables with an `_old` suffix to store the imported data temporarily.

### 3. Convert Old Schema to New Schema
Run the SQL script to transform the old schema (tables with `_old` suffix) into the new schema and remove the temporary `_old` tables.

Execute the following SQL script:
```bash
mysql -h 127.0.0.1 -u your_username -p your_database_name < script/oldSchemaToNew.sql
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

## Migrating Multiple SQLite Databases

If you need to merge data from multiple old SQLite databases into a single new MySQL database, the standard migration process needs modification due to potential duplicate IDs across the databases. Here's a recommended approach:

1. **Process Each SQLite Database Individually:** For *each* old SQLite database (`old_db_1.sqlite`, `old_db_2.sqlite`, etc.):
   a. **Create a Temporary Clean Database:** Set up a *separate*, temporary, clean MySQL database. Run `sail artisan migrate:fresh --seed` on this temporary database.
   b. **Convert SQLite to SQL:** Use the `sqliteToMysql.py` script to convert the current SQLite database to SQL format (e.g., `python3 sqliteToMysql.py old_db_1.sqlite`).
   c. **Import SQL to Temporary DB:** Import the generated `.sql` file into the temporary MySQL database:
      ```bash
      (echo "SET FOREIGN_KEY_CHECKS=0;" && cat script/old_db_1.sql) | mysql -h 127.0.0.1 -u temp_user -p temp_db_name
      ```
   d. **Convert Schema in Temporary DB:** Run the `oldSchemaToNew.sql` script on the temporary database to transform the data:
      ```bash
      mysql -h 127.0.0.1 -u temp_user -p temp_db_name < script/oldSchemaToNew.sql
      ```
   e. **Export Data to JSON:** Export the relevant data from the *now converted* temporary MySQL database into a structured format like JSON.
      ```bash
      sail artisan fiscal-year:export 1 --output exports/old_db_1.json
      ```
      > Output: `storage/app/exports/old_db_1.json`
   f. **Discard Temporary Database:** You can now drop the temporary MySQL database.
2. **Prepare a Clean Target Database:** Start with a clean, migrated, and seeded MySQL database using `sail artisan migrate:fresh --seed`. This will be your final target database.
3. **Import JSON Data into Final Database:** 
   ```bash
   sail artisan fiscal-year:import exports/old_db_1.json 1403 --name="Fiscal Year 1403" --force
   ```
   Don't forget to give access to the current user for this new Fiscal year import.
   
4. **Final Verification:** Thoroughly check the data integrity in the final merged database.

This multi-step process ensures that data from each source database is correctly formatted according to the new schema before being merged, mitigating issues with duplicate primary keys. It requires careful planning and potentially custom scripting for the JSON export and import phases.

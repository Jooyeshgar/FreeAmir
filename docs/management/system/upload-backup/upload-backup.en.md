# Upload a backup

Management → System → Upload Backup imports data from a backup file into a company/fiscal year. Back up important data and test the recovery in a non-production environment first.

1. Enter the destination company name: at most 50 characters, using letters, digits, and spaces.
2. Enter a positive integer for the fiscal year.
3. Select the backup ZIP and choose “Upload.” The file may be at most 100 MB, and the ZIP must contain a readable JSON file.
4. After the success message redirects you to the home page, inspect the company/fiscal year and a sample of documents and balances.

A corrupt file, ZIP without JSON, or invalid JSON is rejected. This guide does not imply that an arbitrary ZIP has a compatible application data structure; see [Download a backup](../backups/backups.en.md) to create a suitable file.

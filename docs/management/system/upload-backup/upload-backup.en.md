# Upload a backup

Management → System → Upload Backup currently asks for a company name and fiscal-year number. Company and fiscal year are separate records; restored data must be linked to a company and a fiscal-year record. The form does not identify a destination company and sends the legacy `name`/`fiscal_year` payload to the import service, so this flow is not aligned with the separate `Company` and `FiscalYear` models. Do not use this page for recovery until the form and service are updated.

The ZIP may be at most 100 MB and must contain a readable JSON file. The import service needs `company_id` and `year` to create a fiscal-year record.

A corrupt file, ZIP without JSON, or invalid JSON is rejected. This guide does not imply that an arbitrary ZIP has a compatible application data structure; see [Download a backup](../backups/backups.en.md) to create a suitable file. After the flow is updated, test recovery in a non-production environment and reconcile balances before relying on it.

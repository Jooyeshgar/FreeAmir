# Fiscal-year operations guide

This guide explains everyday fiscal-year operations, creating a fiscal year, transferring an invoice, exporting/importing data, and closing a period for an accountant.

> **Warning:** Back up your data before creating a new year, bulk transfers, data transfers, or closing accounts. Closing a year and transferring data are sensitive accounting operations and require human review.

## How companies and fiscal years work in Amir

Each fiscal year is stored as a company in Amir. Consequently:

- Selecting a fiscal year changes the user's active scope.
- Numbers, documents, invoices, and balances are checked within that fiscal year.
- Prior-year data is not automatically moved into the new year.
- The user must transfer each needed section when creating the next year or with a transfer tool.

## Create a fiscal year

1. In the company list, choose **Create new company**.
2. Select the source fiscal year (optional).
3. Select the sections to transfer (required if you select a source year).
4. Enter the intended company name and fiscal year.
5. Select **Create** and wait for the operation to finish.
6. Once successful, the new year becomes the active scope.

### Sections available for transfer

The application supports transferring:

- Configurations
- Banks
- Customers
- Products
- Warehouses
- Services
- Accounts (subjects)
- Accounting documents
- Document files
- Invoices
- Cheques
- Employees
- Payroll
- Official holidays
- Tax tables

Dependencies matter. For example, transferring products and customers requires accounts; invoices depend on customers, products/services, warehouses, and related accounts. Do not select a section without its prerequisites.

## Transfer an invoice to another fiscal year

An invoice is transferred from its dedicated page as a fiscal-year transfer. These conditions apply:

- The source invoice must be a current, approved sales invoice.
- The destination fiscal year must be valid and accessible to the user.
- The customer, line items, and dependent accounts must exist in the destination fiscal year.

### Transfer result

A successful transfer creates the invoice and required rows in the destination. The source invoice can no longer be edited, deleted, unapproved, paid, or voided. Finish all necessary corrections and payments before transferring it.

### Conflict response

If the application returns `HTTP 409`, read the message. A conflict usually points to an accounting dependency. Do not repeat the operation without investigating it.

## Backups

You can create or restore backups for companies. Always make a backup before sensitive operations and record its filename and creation date. Restoring a backup may replace current data. Do so only with the right permission and after verifying the file.

## Export and import a fiscal year

Amir provides these commands to a system administrator for file transfer:

```bash
php artisan fiscal-year:export {companyId} --output=storage/app/fiscal-year-export.json
php artisan fiscal-year:import storage/app/fiscal-year-export.json --dry-run
php artisan fiscal-year:import storage/app/fiscal-year-export.json --target-company={companyId}
```

### Export

- The source fiscal-year/company ID must be valid.
- The exported file contains sensitive accounting data; restrict access to it.
- Record the file path and row counts.

### Import

1. Run `--dry-run` first.
2. Resolve structural, mapping, and dependency errors.
3. Back up the destination.
4. Run the import.
5. Reconcile record counts and financial reports with the source.

Do not import data into a non-local environment without the system administrator's approval.

## Fiscal-year closing wizard

The closing wizard has three stages:

1. Pre-closing checks
2. Closing temporary accounts
3. Closing permanent accounts and creating the next year

### Stage 1: Pre-closing checks

Review the checks shown in the wizard before continuing. An accountant's practical checks should include at least:

- Unbalanced or incomplete documents
- Invoices waiting for or ready for approval
- Payments without documents
- Unreasonable customer and supplier balances
- Negative inventory or warehouse discrepancies
- Unapproved ancillary costs
- Tax, cash, and bank discrepancies
- Missing accounts needed for closing

Do not proceed until errors are resolved.

### Stage 2: Close temporary accounts

In this stage, temporary income and expense accounts are closed and their balances are transferred to the current profit-and-loss summary account. Check the proposed document number; it must be unique within the company.

After posting:

- Open the temporary-account closing document.
- Check that the document balances.
- Reconcile the profit-and-loss summary account balance with the profit-and-loss report.

### Stage 3: Close permanent accounts and create the next year

This stage is enabled only after temporary accounts have been closed. Amir closes the balances of permanent accounts and creates the next fiscal year.

Before running it:

- Check the name of the next fiscal year.
- Check the closing and opening document numbers.
- Make a backup.

Afterward, reconcile the source year's closing document with the destination year's opening document.

### Checks after activating the new fiscal year

- [ ] The company name and fiscal year are correct.
- [ ] Required accounts and configurations have been transferred.
- [ ] Customers are linked to the correct accounts.
- [ ] Products, groups, and warehouses were transferred completely.
- [ ] The opening balance matches the prior year's closing balance.
- [ ] Product quantities and inventory across all warehouses match the prior year-end report.
- [ ] Cash and bank balances match the year-end reconciliation.
- [ ] Transferred invoices are locked at source and visible at destination.
- [ ] The opening document balances.
- [ ] User access and active scopes are correct.

## Common errors

| Error | Suggested action |
|---|---|
| A dependency was not transferred | Transfer the required account, customer, product/service, or configuration first. |
| Duplicate document number | Enter another number that is unique within the fiscal year. |
| Invoice cannot be transferred | Check its type, status, and dependencies. |
| Conflict response 409 | Review the dependency message or warning, then decide how to proceed. |
| Wizard will not advance | Resolve pre-closing errors or the missing temporary-account closing document. |
| Opening and closing balances differ | Review the closing documents, balances, and account mappings. |
| Import failed | Check `--dry-run`, the file structure, and destination mappings first. |

## Accountant's final checklist

- [ ] A valid backup has been created.
- [ ] All source-year documents balance and are final.
- [ ] No invoice, payment, or ancillary cost remains unresolved.
- [ ] Customer, supplier, cash, and bank balances are approved.
- [ ] Inventory quantities and values have been checked.
- [ ] Temporary accounts have been closed.
- [ ] Closing and opening documents reconcile.
- [ ] The next fiscal year and transferred sections have been checked.

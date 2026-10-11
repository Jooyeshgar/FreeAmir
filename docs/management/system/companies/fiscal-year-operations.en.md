# Fiscal-year operations guide

This guide explains everyday fiscal-year operations, creating a fiscal year, transferring an invoice, exporting/importing data, and closing a period for an accountant.

> **Warning:** Back up your data before creating a new year, bulk transfers, data transfers, or closing accounts. Closing a year and transferring data are sensitive accounting operations and require human review.

## How companies and fiscal years work in Amir

A company is the business identity and can have multiple fiscal years. Each fiscal year is a separate period for accounting and reporting; documents, invoices, and balances are linked to that year's ID. User access is also assigned by fiscal year, so access to one year does not grant access to the company's other years.

- Selecting a fiscal year changes the user's active scope.
- Numbers, documents, invoices, and balances are checked within that fiscal year.
- Prior-year data is not automatically moved into the new year.
- The user must transfer each needed section when creating the next year or with a transfer tool.

## Create a fiscal year

1. In the Fiscal Years section, choose **Create Fiscal Year**.
2. Enter the destination company and fiscal-year number.
3. If needed, select a source fiscal year. It must belong to the same destination company.
4. If you selected a source year, choose the sections to copy. The form selects all sections by default.
5. Select **Create** and wait for the operation to finish.
6. After creation, the new fiscal-year page opens. To work in that year, select it from the active fiscal-year selector; creating a year does not change the active scope by itself.

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

On the Backup page, select the fiscal year you need. The export is built from that fiscal year and does not include the company's other fiscal years. Keep a backup before sensitive operations and record its filename and creation date.

Uploading a backup asks for a company name and fiscal-year number, then creates a new company and fiscal year for the imported data. The form does not let you choose an existing company as the destination. See the download and upload guides for details about each page.

## Export and import a fiscal year

The export command is available to system administrators. The import command is not aligned with the new models; see the developer guide for details.

```bash
php artisan fiscal-year:export {fiscalYearId} --output=storage/app/fiscal-year-export.json
```

### Export

- The source fiscal-year ID must be valid.
- The exported file contains sensitive accounting data; restrict access to it.
- Record the file path and row counts.

### Import

The current command accepts a name and year instead of a destination `company_id`; do not use it to create a fiscal year until its payload matches the service. See the [developer export/import guide](../../../developer/FiscalYearExportImport.en.md). After the command is updated, import in a non-production environment first, back up the destination, and reconcile record counts and financial reports with the source.

## Fiscal-year closing wizard

Before running the wizard, select the fiscal year you want to close from the active fiscal-year selector. The wizard closes that fiscal year only.

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

In this stage, temporary income and expense accounts are closed and their balances are transferred to the current profit-and-loss summary account. Check the proposed document number; it must be unique within the fiscal year.

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

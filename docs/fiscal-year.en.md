# Fiscal Year in Amir

**[نسخه فارسی](fiscal-year.md)**  
**[Back to documentation index](README.en.md)**

## What Is a Fiscal Year?

A fiscal year is a defined time period used for bookkeeping, reporting, and closing accounts. Many Iranian businesses align the fiscal year with the Solar Hijri year, but each company may use a different period depending on legal or management needs.

In Amir, the fiscal year is a primary boundary for financial data. Documents, invoices, products, configurations, and reports should be created and reviewed in the correct company/fiscal-year context.

## Why Fiscal Years Matter

- They separate financial reports for one period from other periods.
- They make account closing and balance transfer controllable.
- They reduce the risk of recording documents in the wrong period.
- They make data transfer from one fiscal year to the next traceable.

## When to Create a New Fiscal Year

Create a new fiscal year when:

- The current financial period has ended.
- The company wants to record documents and invoices for a new period separately.
- Opening balances, products, customers, banks, and configurations need to move into the next period.

## Suggested Creation Flow

1. Back up the current data.
2. Review and finalize important documents and invoices from the previous year.
3. Create the new fiscal year/company context in the system.
4. Transfer or redefine base configurations, banks, customers, groups, products, and subjects.
5. Review opening balances.
6. Compare a few key reports with the previous year to confirm the transfer.

## Project Tools

The project includes two Artisan commands for fiscal-year data transfer:

- `fiscal-year:export`: exports source fiscal-year data
- `fiscal-year:import`: imports exported data into a new fiscal year

Full details are available in [FiscalYearExportImport.md](FiscalYearExportImport.md).

## Cautions

- Test fiscal-year operations in a non-production environment first.
- Back up the destination database before importing data.
- After transfer, review inventory, customer balances, bank balances, and opening documents.
- Any change to fiscal-year transfer logic should include tests and accounting review.

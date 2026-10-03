# Amir Programmer Guide

**[نسخه فارسی](README.md)**  
**[Back to documentation index](../menu/README.en.md)**

This index is for developers and contributors. If you plan to change Laravel code, the database, tests, services, or accounting logic, start here.

## Suggested Reading Path

1. [Project structure](project-structure.en.md)
2. [Database guide](database-guide.en.md)
3. [Testing guide](testing-guide.en.md)
4. [Accounting basics](accounting-basics.en.md)
5. [Fiscal year](../management/system/companies/fiscal-year.en.md)
6. [Fiscal-year export/import](FiscalYearExportImport.en.md)

## Technical Documentation

| File | Purpose |
|---|---|
| [project-structure.md](project-structure.en.md) | Laravel architecture, folders, and code organization |
| [database-guide.md](database-guide.en.md) | Tables, relationships, and database notes |
| [testing-guide.md](testing-guide.en.md) | Running tests and writing Feature and Unit tests |
| [../script/README.md](../../script/README.md) | Data migration and utility scripts |

## Accounting Domain Documentation for Development

| File | Purpose |
|---|---|
| [accounting-basics.md](accounting-basics.en.md) | Debit/credit concepts, balanced documents, and transaction storage |
| [user/inventory-costing.en.md](../warehouse/products/inventory-costing.en.md) | User-facing explanation of COGS and the moving weighted average method |
| [return-sell-return-buy.md](../invoices/sells/return-sell/return-sell-return-buy.en.md) | Recording sales returns and purchase returns |
| [fiscal-year.en.md](../management/system/companies/fiscal-year.en.md) | Fiscal-year concept and creation flow |
| [FiscalYearExportImport.en.md](FiscalYearExportImport.en.md) | Fiscal-year export and import commands |

## Important Rules for Code Changes

- Do not break accounting document balance.
- Company and fiscal-year data must remain separated.
- Financial changes must include Feature or Unit tests.
- Keep controllers thin and put business logic in services.
- Changes to transaction builders or cost-of-goods calculations need accounting-domain review.

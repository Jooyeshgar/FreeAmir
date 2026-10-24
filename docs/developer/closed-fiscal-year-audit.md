# Closed fiscal-year mutation audit (#427)

The reviewed route inventory is `tests/Fixtures/closed-fiscal-year-routes.json`.
Each entry records HTTP method, route name, URI, and expected closure behavior.
It contains 397 web, employee-portal, and API routes: 228 blocked mutation or
preparation entry points, 164 read-only/shared/new-record entry points, and five
closing-workflow entry points. `RemainingFiscalYearMutationTest` checks every
entry against the middleware and requires the inventory to match the registered
routes carrying `fiscal-year-open`.

## Ownership and verification

| Domain | Ownership boundary | Regression evidence |
| --- | --- | --- |
| Customers, products, services, their groups, subjects, banks/accounts, chequebooks, cheques, warehouses | Record `fiscal_year_id`; explicit creation target; both years when moving ownership | `RemainingFiscalYearMutationTest`, `CustomerTest`, `CustomerGroupTest`, `ChequeManagementTest`, `SubjectTransferTest` |
| Customer comments | Actual customer reached without the active-year scope; both route-bound child and parent; posted destination customer | `RemainingFiscalYearMutationTest` nested-comment and direct-service cases |
| Inventory transfers | Product, source warehouse, destination warehouse; each must be open and all must belong to the same year | `RemainingFiscalYearMutationTest`, `WarehouseTest` |
| CSV imports | Explicit destination fiscal year before parsing or writing | `RemainingFiscalYearMutationTest`, product/service/customer import-export suites |
| Settings and monthly forecasts | Selected year; bound budget for deletion | `RemainingFiscalYearMutationTest`, `ConfigControllerTest`, `MonthlyBudgetTest` |
| Documents, invoices, payments, ancillary costs, attachments, tax submission history | Actual record or owning document/invoice, including nested records hidden by active-year scopes | `ClosedFiscalYearMutationGuardTest`, `ClosedYearNestedOperationControlsTest`, `ApiTest` |
| HR, attendance, payroll, employee portal | Direct year or owning employee/decree/attendance/payroll/request; explicit target for creation | `ClosedFiscalYearMutationGuardTest`, route inventory, `ApiTest` |
| Transfers between years | Source and destination must both be open | `FiscalYearTransferTest`, subject-transfer guard cases |
| Backup/restore | Export reads the source. Import always creates a new fiscal year and remaps records; it does not restore over an existing year | `BackupControllerTest`; `FiscalYearService::importData` creates the destination before importing |
| Shared company details, global management records, tokens | Existing authorization applies independently of fiscal-year closure | `CompanyControllerFiscalYearTest`; route exemptions |
| Closing and opening documents | Dedicated closing-state workflow; ordinary mutation endpoints stay blocked | `FiscalYearClosingRecalculationTest` |

All ordinary closed-year mutations reject super-admins as well as ordinary users.
Show, search, report, print, and export requests remain readable. Create/edit,
import, transfer, lifecycle, and forecast controls use the localized closure
explanation. The closing workflow retains its existing state validation and
recalculation exception.

Routes outside the inventory were reviewed in `routes/web.php` and
`routes/api.php`: authentication, registration, locale, global management,
company registration, and API company listing do not mutate existing fiscal-year
records. Newly introduced fiscal-year routes must carry `fiscal-year-open` and
be added to the inventory with an ownership regression test.

## Verification limitations on the reviewed baseline

Container tests use SQLite. Twelve failures reproduced with all implementation
changes removed: six monthly-budget subject-validation failures, three backup
fixture/enum/ID-remapping failures, one transfer enum-conversion failure, and two
warehouse filtering/SQL-lock assertions. These remain baseline limitations;
the closure-specific suites, cheque suite, configuration suite, import/export
suites, customer/group suites, subject transfers, company-detail behavior, and
closing recalculation checks passed. No interactive browser verification was
performed; feature tests inspect rendered disabled controls and Blade compilation
checks all templates.

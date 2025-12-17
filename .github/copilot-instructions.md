# Copilot Instructions for FreeAmir

Quick, actionable guidance for AI coding agents to be productive in this repo.

## Big picture (what matters) 🔧
- Codebase is a Laravel application focused on accounting logic (Persian/IR localizations). Read: `README.md` and `docs/project-structure.md` first.
- Business logic lives mostly in the **service layer**: `app/Services/` (e.g. `InvoiceService`, `FiscalYearService`, `CostOfGoodsService`). Transaction-building logic is encapsulated in *builders* (e.g. `InvoiceTransactionBuilder`, `AncillaryCostTransactionBuilder`).
- Data model constraints and cross-cutting rules (multi-company, fiscal-year scoping, balanced accounting) are enforced via Scopes/Middleware and Services: see `app/Models/Scopes/FiscalYearScope.php` and `app/Http/Middleware/DefaultCompany.php`.
- Important invariants: do not violate document balance, always use DB transactions for multi-step account changes, and separate company data.

## Developer workflows & commands ⚙️
- Development container: prefer Laravel Sail when present. Common commands:
  - Start containers: `sail up -d`
  - Frontend dev: `sail npm run dev` (Vite)
  - Build assets: `sail npm run build`
  - Run tests: `sail artisan test` or `./vendor/bin/phpunit` (use `--filter` for single tests)
  - Format: `./vendor/bin/pint`
  - Migrate/seed: `sail artisan migrate --seed`
  - Deployment helper: `envoy run deploy` (see `Envoy.blade.php`)
- For one-off local commands without Sail, run `composer`, `php artisan`, `npm` directly.

## Where to look for examples / patterns 🔎
- Validation: FormRequests under `app/Http/Requests/` (e.g. `StoreInvoiceRequest.php`) — prefer request objects for input validation.
- Controllers keep request/response thin; heavy lifting should be done by Services (see `app/Http/Controllers/InvoiceController.php`).
- Transaction logic & invariants: `app/Services/*TransactionBuilder.php` and `app/Services/*Service.php`.
- Console commands for fiscal operations: `app/Console/Commands/FiscalYearExportCommand.php` and `FiscalYearImportCommand.php` — good references for complex I/O or batch operations.
- Helpers & autoloaded utility functions: `app/Helpers/` (e.g. `NumberToWordHelper.php`).

## Project-specific conventions & rules ✅
- Naming: **PascalCase** for classes, **camelCase** for methods/variables, **snake_case** for DB fields (see `CLAUDE.md`).
- Use PHP 8+ features consistently (typed properties, arrow functions, etc.).
- Always wrap multi-step DB changes in transactions. The repository emphasizes this as a golden rule (docs and code use `DB::transaction()` frequently).
- Keep Persian explanatory comments and business notes where present; some domain knowledge (accounting terms) appears in Persian docs and comments.

## Tests & CI expectations 🧪
- Tests live in `tests/` and there is a `docs/testing-guide.md` describing expectations.
- New features must include focused unit/feature tests; use `sail artisan test --filter` for targeted runs.
- Use `./vendor/bin/pint` for formatting before committing.

## Integration & external pieces 🔗
- Uses Laravel Sail (Docker-based dev), Pint (formatter), Envoy (deploy). See `docker-compose.yml`, `Envoy.blade.php`, and composer `vendor/bin` tools.
- Database migration and migration-from-SQLite helpers are in `script/` (see `script/README.md`)—use these scripts for data-migration tasks.

## Safety & domain constraints ⚠️
- Accounting invariants are critical: do not change posting/transaction behaviors without corresponding tests and domain sign-off.
- Fiscal-year migration/import/export is sensitive—use the provided console commands and `script/` guidance; always test on a copy.

## Helpful files to reference directly 📁
- `app/Services/` — core business logic
- `app/Http/Requests/` — validation patterns
- `app/Console/Commands/FiscalYearExportCommand.php` and `FiscalYearImportCommand.php` — batch/scoped operations
- `app/Models/Scopes/FiscalYearScope.php` — scoping by fiscal year
- `docs/project-structure.md`, `docs/testing-guide.md`, `README.md`, `CLAUDE.md` — onboarding & process

---

If any of the above sections are unclear or you'd like more examples (small code snippets showing common PR-style changes), tell me which section to expand and I will iterate. 🙌

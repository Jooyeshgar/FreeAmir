# FreeAmir Project Structure

This guide describes the overall organization of the FreeAmir codebase. FreeAmir is built on **Laravel** and follows the **MVC** architecture.

## 🏗️ Overall structure

```text
FreeAmir/
├── app/          # Application logic
│   ├── Console/  # Custom Artisan commands
│   ├── Enums/    # Fixed-value types
│   ├── Helpers/  # Helper functions
│   ├── Http/     # Controllers, middleware, and requests
│   ├── Models/   # Eloquent models
│   ├── Providers/# Service providers
│   ├── Services/ # Complex business logic
│   └── View/     # View components
├── config/       # Configuration
├── database/     # Migrations and seeders
├── docs/         # Documentation
├── public/       # Public files
├── resources/    # Views, CSS, and JavaScript
├── routes/       # Route definitions
├── storage/      # Temporary files and logs
├── tests/        # Automated tests
└── vendor/       # Composer dependencies
```

## 📂 Main paths

### `app/` — the application core

#### `Console/Commands/`

Custom Artisan commands perform tasks such as fiscal-year export and import. The Persian guide illustrates `php artisan fiscal-year:export --year=1403` and `php artisan fiscal-year:import --file=data.json --year=1404`; check the current command signatures before running an operation.

#### `Enums/`

Enums hold fixed values. For example, `FiscalYearSection` identifies exportable sections such as `subjects`, `customers`, and `products`.

#### `Helpers/`

`helpers.php` contains common functions, `jdf.php` handles Persian date conversion, and `NumberToWordHelper.php` converts numbers to words.

#### `Http/`

The HTTP layer contains `Controllers/` (including `Auth/`, `Management/`, document and invoice controllers), `Middleware/`, and `Requests/` for request validation. The older source guide also names `Kernel.php`; verify its location against the current Laravel version.

#### `Models/`

Eloquent models manage stored data and relationships:

- `Document` and `Transaction` represent accounting documents and their entries.
- `Subject` models the parent/child account hierarchy.
- `Company` stores business identity and has many fiscal years.
- `FiscalYear` stores a company's accounting period, closing state, and closing/opening document links.
- `User` holds user data and fiscal-year access relationships.
- `Customer`/`CustomerGroup`, `Product`/`ProductGroup`, and `Invoice`/`InvoiceItem` represent their respective business records.
- `Bank`, `BankAccount`, `Cheque`, and `ChequeHistory` represent banking and check records.
- `Config` and `Payment` hold system configuration and payments.

`Models/Scopes/FiscalYearScope.php` restricts related records to the active fiscal-year ID. It reads the active ID through `getActiveFiscalYear()`, which uses request configuration or the `active-fiscal-year-id` cookie. The `fiscal_year_user` many-to-many relationship assigns user access by fiscal year. `Company` and `FiscalYear` are separate entities: each fiscal year belongs to one company, and a company can have multiple years.

#### `Services/`

Services contain complex business logic. `DocumentService` creates documents and enforces posting rules; `FiscalYearService` handles fiscal-year data export and import. Controllers should call services for these operations.

## 🎛️ Configuration (`config/`)

Important examples are `config/app.php` for application defaults such as the Persian locale, `config/database.php` for the database connection (for example, `DB_CONNECTION=mysql`), and `config/permission.php` for roles and permissions.

## 📊 Database (`database/`)

### `migrations/`

Migrations define table structure. A document migration may declare an ID, a nullable numeric document number, title and dates, creator and approver foreign keys, a `fiscal_year_id` foreign key to `fiscal_years`, and timestamps. Refer to the actual migrations for the current schema.

### `seeders/`

`DatabaseSeeder.php` inserts essential data; `DemoSeeder.php` supplies demonstration data.

## 🎨 Resources (`resources/`)

### `views/`

Blade templates are organized into layouts, document pages, reports, authentication pages, and reusable components.

### `js/` and `css/`

Vite manages JavaScript and CSS assets.

## 🛣️ Routing (`routes/`)

### `web.php`

`routes/web.php` defines web routes. The original guide includes a long route snapshot: login, logout and locale switching; password recovery; feature-gated registration and verification; about and company creation; management dashboard, activity logs, settings, users, roles, and permissions; and the remaining application modules. Those routes attach authentication, feature, and permission middleware as appropriate. Because routes change, consult the current [`routes/web.php`](../../routes/web.php) for exact URLs, names, controllers, and middleware. The menu guide hierarchy is derived from the application menu, while route names identify individual destinations.

### `api.php`

Fiscal-year-scoped API requests are intended to use Sanctum authentication, `SetApiFiscalYear`, and specific `check-permission` rules. `SetApiFiscalYear` expects a `fiscal_year` route parameter and should check the user's `fiscalYears()` assignment before setting the active ID. **Current limitation:** `routes/api.php` still uses `companies/{company}`, while the middleware reads `fiscal_year` and checks the removed `companies()` relation. Those routes and the access check are not aligned with the refactor. Consult the current [`routes/api.php`](../../routes/api.php) before adding routes to a group.

## 🧪 Tests (`tests/`)

The standard Laravel layout separates feature and unit tests. The source guide uses `Feature/ExampleTest.php` (home page response) and `Unit/ExampleTest.php` (`true` assertion) as examples. Add new tests with `php artisan make:test` or extend an appropriate existing test. See the [Testing Guide](testing-guide.en.md).

## 🔧 Development tools

### Composer

`composer install` installs PHP dependencies, `composer dump-autoload` rebuilds autoload metadata, and `composer update` updates packages.

### NPM

`npm install` installs JavaScript dependencies, `npm run dev` starts development assets, and `npm run build` produces a build.

### Artisan

Examples are `php artisan migrate` (migrations), `db:seed` (seeders), `make:model` (new model), and `serve` (development server).

## 📝 Naming conventions

### Classes

Controllers use PascalCase plus `Controller` (`DocumentController`), models use singular PascalCase (`Document`), and services use PascalCase plus `Service` (`DocumentService`).

### Files

Views use kebab-case (`document-create.blade.php`); migration filenames begin with a date and use snake_case (`2024_01_01_create_documents_table`).

### Variables

PHP variables use camelCase (`$fiscalYear`), whereas database columns use snake_case (`fiscal_year_id`).

### Routes

URLs use kebab-case (`/customer-groups`); route names use dot notation (`documents.create`).

## 🔒 Security

### Middleware

`auth` authenticates users and `check-permission` checks authorization, alongside other Laravel middleware.

### Permissions

The application uses Spatie Permission. The source illustrates a controller authorization call (`$this->authorize('documents.create')`, marked as not used there) and Blade's `@can('documents.edit')` directive for conditional controls.

## 🚀 Optimization

### Caching

Laravel can cache configuration (`php artisan config:cache`), routes (`route:cache`), and views (`view:cache`).

### Database

Index frequently queried columns, use eager loading for relationships, and paginate large lists.

Read the current project structure before making changes, and follow patterns already used in the codebase.

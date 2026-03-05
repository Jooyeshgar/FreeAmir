# Installation Guide

## Option 1: Standard Installation (PHP / Composer / npm)

### Prerequisites

*   PHP >= 8.2
*   Composer
*   MySQL database
*   Node.js >= 18.0.0

### Steps

**1. Clone the repository:**

```bash
git clone https://github.com/Jooyeshgar/FreeAmir.git
cd FreeAmir
```

**2. Install PHP dependencies:**

```bash
composer install
```

**3. Configure environment:**

Copy `.env.example` to `.env` and set your database credentials:

```bash
cp .env.example .env
```

**4. Generate application key:**

```bash
php artisan key:generate
```

**5. Run database migrations:**

```bash
php artisan migrate
```

**6. Seed the database with sample data:**

```bash
php artisan db:seed
```

Optional — seed demo data:

```bash
php artisan db:seed --class DemoSeeder
```

**7. Install frontend packages:**

```bash
npm install
```

**8. Run the Vite development server:**

```bash
npm run dev
```

**9. Start the development server:**

```bash
php artisan serve
```

Access the application at http://localhost:8000.

---

## Option 2: Laravel Sail (Docker)

### Prerequisites

*   Docker & Docker Compose
*   Composer (for the initial install step only)

### Steps

**1. Clone the repository:**

```bash
git clone https://github.com/Jooyeshgar/FreeAmir.git
cd FreeAmir
```

**2. Install PHP dependencies on the host (first time only):**

```bash
composer install
```

**3. Start Sail containers:**

```bash
sail up -d
```

**4. Install PHP dependencies inside Sail:**

```bash
sail composer install
```

**5. Configure environment:**

```bash
cp .env.example .env
```

Update `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` to match the Sail defaults (or your own values).

**6. Generate application key:**

```bash
sail artisan key:generate
```

**7. Run database migrations:**

```bash
sail artisan migrate
```

**8. Seed the database with sample data:**

```bash
sail artisan db:seed
```

Optional — seed demo data:

```bash
sail artisan db:seed --class DemoSeeder
```

**9. Install frontend packages:**

```bash
sail npm install
```

**10. Run the Vite development server:**

```bash
sail npm run dev
```

Access the application at http://localhost (Sail default port).

For more details refer to the official **[Sail documentation](https://laravel.com/docs/sail)**.

---

## Database Migration from Older Version

If you are migrating from the older SQLite-based version of Amir, please refer to the [Database Migration Guide](script/README.md) for detailed instructions.

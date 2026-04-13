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

## Option 3: Production Docker Compose

This option is intended for self-hosted production deployments. It uses a lean, purpose-built Docker
image (no dev tools, no Xdebug, no PostgreSQL/MongoDB). The bootstrap script automatically handles
key generation, migrations, and cache warming on every container start.

### Prerequisites

*   Docker >= 24
*   Docker Compose >= 2.20

### Steps

**1. Clone the repository:**

```bash
git clone https://github.com/Jooyeshgar/FreeAmir.git
cd FreeAmir
```

**2. Configure environment:**

```bash
cp docker/.env.example docker/.env
```

Open `docker/.env` and set strong values for at least these variables:

| Variable | Description |
|---|---|
| `APP_URL` | Public URL of the application (e.g. `https://yourdomain.com`) |
| `DB_PASSWORD` | MariaDB application user password |
| `DB_ROOT_PASSWORD` | MariaDB root password |
| `APP_PORT` | Host port to expose the app (default `80`) |

**3. Build and start the containers:**

```bash
docker compose -f docker/docker-compose.yml --env-file docker/.env up -d --build
```

The bootstrap script inside the container will automatically:
- Generate the `APP_KEY` if not already set in the environment
- Run `php artisan migrate --force`
- Seed the database (skipped if already seeded)
- Warm up config, route, and view caches

**4. Check container logs:**

```bash
docker compose -f docker/docker-compose.yml --env-file docker/.env logs -f app
```

Access the application at the `APP_URL` you configured (default: http://localhost).

**5. (Optional) Start phpMyAdmin for database management:**

phpMyAdmin is included but disabled by default via a Docker Compose profile. To enable it:

```bash
docker compose -f docker/docker-compose.yml --env-file docker/.env --profile tools up -d
```

Access phpMyAdmin at http://localhost:8080 (or the port set in `PMA_PORT`).

**6. Stop the containers:**

```bash
docker compose -f docker/docker-compose.yml --env-file docker/.env down
```

To also remove named volumes (⚠️ destroys database data):

```bash
docker compose -f docker/docker-compose.yml --env-file docker/.env down -v
```

---

## Database Migration from Older Version

If you are migrating from the older SQLite-based version of Amir, please refer to the [Database Migration Guide](script/README.md) for detailed instructions.

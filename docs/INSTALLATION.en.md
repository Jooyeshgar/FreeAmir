# Installation Guide

## Table of Contents

1. [Production — Docker Compose (Recommended)](#option-1-production--docker-compose-recommended)
2. [All-in-One — Single Docker Command (Testing only)](#option-2-all-in-one--single-docker-command-testing-only)
3. [Standard Installation — PHP + MariaDB](#option-3-standard-installation--php--mariadb)

---

## Option 1: Production — Docker Compose (Recommended)

Uses pre-built images from GitHub Container Registry. No source code or build tools required — just Docker and a `.env` file.

The app image bootstrap script automatically:
- Generates `APP_KEY` if not set
- Runs `php artisan migrate --force`
- Seeds the database (skipped if already seeded)
- Warms up config, route, and view caches

### Prerequisites
- Docker >= 24
- Docker Compose >= 2.20

### Steps

**1. Download the Docker Compose file and environment template:**
```bash
mkdir freeamir && cd freeamir
curl -O https://raw.githubusercontent.com/Jooyeshgar/FreeAmir/main/docker/production/docker-compose.prebuilt.yml
curl -O https://raw.githubusercontent.com/Jooyeshgar/FreeAmir/main/docker/production/.env.example
cp docker-compose.prebuilt.yml docker-compose.yml
cp .env.example .env
```

**2. Edit `.env` and set your passwords and URL:**

| Variable | Description | Default |
|---|---|---|
| `APP_URL` | Public URL of the application | `http://localhost` |
| `APP_PORT` | Host port to expose the app | `80` |
| `DB_PASSWORD` | MariaDB application user password | `change_me_strong_password` |
| `DB_ROOT_PASSWORD` | MariaDB root password | `change_me_root_password` |
| `DB_DATABASE` | Database name | `freeamir` |
| `DB_USERNAME` | Database user | `freeamir` |
| `PMA_PORT` | phpMyAdmin host port (optional) | `8080` |

**3. Pull images and start the containers:**
```bash
docker compose up -d
```

**4. Check startup logs:**
```bash
docker compose logs -f php-fpm
```

Access the application at the `APP_URL` you configured (default: http://localhost).

**5. (Optional) Start phpMyAdmin for database management:**
```bash
docker compose --profile tools up -d
```
Access phpMyAdmin at http://localhost:8080.

**6. Stop the containers:**
```bash
docker compose down
```

> ⚠️ To also remove all data volumes (irreversible):
> ```bash
> docker compose down -v
> ```

---

## Option 2: All-in-One — Single Docker Command (Testing only)

> ⚠️ **Not recommended for production.** This image bundles PHP-FPM, Nginx, and MariaDB in a single container for quick evaluation. Data is lost when the container is removed unless a volume is mounted.

### Prerequisites
- Docker >= 24

### Steps

**Pull and run:**
```bash
docker run -d --name freeamir -p 80:80 -v freeamir-data:/var/lib/mysql ghcr.io/jooyeshgar/freeamir-all-in-one:latest
```

Access the application at http://localhost once startup completes.

> 💡 To customise the URL or database credentials, pass environment variables:
> ```bash
> docker run -d --name freeamir -p 80:80 \
>   -e APP_URL=http://your-domain.com \
>   -e DB_PASSWORD=secret \
>   -v freeamir-data:/var/lib/mysql \
>   ghcr.io/jooyeshgar/freeamir-all-in-one:latest
> ```

**Check startup progress:**
```bash
docker logs -f freeamir
```

**Stop and remove:**
```bash
docker stop freeamir && docker rm freeamir
```

---

## Option 3: Standard Installation — PHP + MariaDB

Install directly on your server or workstation with PHP, Composer, Node.js, and MariaDB.

### Prerequisites
- PHP >= 8.2 with extensions: `pdo_mysql`, `gd`, `intl`, `zip`, `bcmath`, `mbstring`, `xml`, `opcache`
- Composer
- MariaDB >= 10.6 (or MySQL >= 8.0)
- Node.js >= 18.0.0

### Steps

**1. Clone the repository:**
```bash
git clone https://github.com/Jooyeshgar/FreeAmir.git
cd FreeAmir
```

**2. Install PHP dependencies:**
```bash
composer install --no-dev --optimize-autoloader
```

**3. Configure environment:**
```bash
cp .env.example .env
```
Edit `.env` and set `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `APP_URL` to match your environment.

**4. Generate application key:**
```bash
php artisan key:generate
```

**5. Run database migrations:**
```bash
php artisan migrate
```

**6. Seed the database:**
```bash
php artisan db:seed
```
Optional — seed with demo data:
```bash
php artisan db:seed --class DemoSeeder
```

**7. Warm up application caches:**
```bash
php artisan optimize
```

**8. Install and build frontend assets:**
```bash
npm install
npm run build
```

**9. Configure your web server** to serve the `public/` directory and point the document root there. For a quick local test:
```bash
php artisan serve
```
Access the application at http://localhost:8000.

---

## Default Login

After seeding, all users share the password **`password`**. Available accounts:

| Email | Roles |
|---|---|
| `admin@example.com` | Super-Admin, Employee |
| `accountant@example.com` | Accountant, Employee |
| `seller@example.com` | Seller, Employee |
| `warehouse@example.com` | Warehousekeeper, Employee |
| `seller-warehouse@example.com` | Seller, Warehousekeeper, Employee |
| `accountant-seller-warehouse@example.com` | Accountant, Seller, Warehousekeeper, Employee |
| `employee@example.com` | Employee |

---

## Database Migration from Older Version

See [Database Migration Guide](database-guide.md) for migrating from the older SQLite-based version.

<div dir="rtl">

# راهنمای نصب

## فهرست مطالب

1. [فایل اجرایی ویندوز (.exe)](#فایل-اجرایی-ویندوز-exe)
2. [محیط عملیاتی — Docker Compose (پیشنهادی)](#روش-۱-محیط-عملیاتی--docker-compose-پیشنهادی)
3. [همه-در-یک — دستور تکی Docker (فقط برای آزمایش)](#روش-۲-همه-در-یک--دستور-تکی-docker-فقط-برای-آزمایش)
4. [نصب استاندارد — PHP + MariaDB](#روش-۳-نصب-استاندارد--php--mariadb)


## فایل اجرایی ویندوز (.exe)

> ⚠️ **برای محیط عملیاتی توصیه نمی‌شود.** این گزینه برای آزمایش سریع در ویندوز طراحی شده است.

### مراحل

**۱. دانلود فایل:**

فایل اجرایی ویندوز را از بخش [نسخه ها](https://github.com/Jooyeshgar/FreeAmir/releases/) دانلود کنید.

**۲. نصب:**

فایل `.exe` دانلود شده را اجرا کنید. برنامه توسط فایل نصب، نصب شده و قابل اجرا خواهد بود.


## روش ۱: محیط عملیاتی — Docker Compose (پیشنهادی)

از ایمیج‌های از پیش ساخته‌شده در GitHub Container Registry استفاده می‌کند. نیازی به کد منبع یا ابزارهای Build نیست — فقط Docker و یک فایل `.env` کافی است.

### پیش‌نیازها
- Docker >= 24
- Docker Compose >= 2.20

### مراحل

**۱. دانلود فایل Docker Compose و متغیرهای محیطی:**
```bash
mkdir freeamir && cd freeamir
curl -O https://raw.githubusercontent.com/Jooyeshgar/FreeAmir/main/docker/production/docker-compose.prebuilt.yml
curl -O https://raw.githubusercontent.com/Jooyeshgar/FreeAmir/main/docker/production/.env.example
cp docker-compose.prebuilt.yml docker-compose.yml
cp .env.example .env
```

**۲. ویرایش فایل `.env` و تنظیم رمزهای عبور و آدرس:**

| متغیر | توضیح | مقدار پیش‌فرض |
|---|---|---|
| `APP_URL` | آدرس عمومی برنامه | `http://localhost` |
| `APP_PORT` | پورت میزبان برای نمایش برنامه | `80` |
| `DB_PASSWORD` | رمز عبور کاربر پایگاه داده MariaDB | `change_me_strong_password` |
| `DB_ROOT_PASSWORD` | رمز عبور root در MariaDB | `change_me_root_password` |
| `DB_DATABASE` | نام پایگاه داده | `freeamir` |
| `DB_USERNAME` | نام کاربری پایگاه داده | `freeamir` |
| `PMA_PORT` | پورت میزبان phpMyAdmin (اختیاری) | `8080` |

**۳. دریافت ایمیج‌ها و راه‌اندازی کانتینرها:**
```bash
docker compose up -d
```

**۴. مشاهده لاگ‌های راه‌اندازی:**
```bash
docker compose logs -f php-fpm
```

پس از راه‌اندازی، برنامه را در آدرسی که در `APP_URL` تنظیم کرده‌اید باز کنید (پیش‌فرض: http://localhost).

> 💡 **(اختیاری) بارگذاری داده‌های نمونه** پس از راه‌اندازی کانتینرها:
> ```bash
> docker compose exec php-fpm php artisan db:seed --class DemoSeeder
> ```

**۵. (اختیاری) راه‌اندازی phpMyAdmin برای مدیریت پایگاه داده:**
```bash
docker compose --profile tools up -d
```
phpMyAdmin در آدرس http://localhost:8080 در دسترس خواهد بود.

**۶. توقف کانتینرها:**
```bash
docker compose down
```


## روش ۲: همه-در-یک — دستور تکی Docker (فقط برای آزمایش)

> ⚠️ **برای محیط عملیاتی توصیه نمی‌شود.** این ایمیج شامل PHP-FPM، Nginx و MariaDB در یک کانتینر است و برای ارزیابی سریع طراحی شده. اگر volume نصب نشود، با حذف کانتینر تمام داده‌ها از بین می‌روند.

### پیش‌نیازها
- Docker >= 24

### مراحل

**دریافت و اجرا:**
```bash
docker run -d --name freeamir -p 80:80 -v freeamir-data:/var/lib/mysql ghcr.io/jooyeshgar/freeamir-all-in-one:latest
```

پس از اتمام راه‌اندازی، برنامه در آدرس http://localhost در دسترس خواهد بود.

> 💡 **(اختیاری) بارگذاری داده‌های نمونه** پس از راه‌اندازی:
> ```bash
> docker exec freeamir php artisan db:seed --class DemoSeeder
> ```

> 💡 برای سفارشی‌سازی آدرس یا اطلاعات پایگاه داده، متغیرهای محیطی را ارسال کنید:
> ```bash
> docker run -d --name freeamir -p 80:80 \
>   -e APP_URL=http://your-domain.com \
>   -e DB_PASSWORD=secret \
>   -v freeamir-data:/var/lib/mysql \
>   ghcr.io/jooyeshgar/freeamir-all-in-one:latest
> ```

**مشاهده پیشرفت راه‌اندازی:**
```bash
docker logs -f freeamir
```

**توقف و حذف:**
```bash
docker stop freeamir && docker rm freeamir
```


## روش ۳: نصب استاندارد — PHP + MariaDB

مستقیما روی سرور یا سیستم شخصی با PHP، Composer، Node.js و MariaDB نصب کنید.

### پیش‌نیازها
- PHP >= 8.2 با افزونه‌های: `pdo_mysql`، `gd`، `intl`، `zip`، `bcmath`، `mbstring`، `xml`، `opcache`
- Composer
- MariaDB >= 10.6 (یا MySQL >= 8.0)
- Node.js >= 18.0.0

### مراحل

**۱. Clone کردن مخزن:**
```bash
git clone https://github.com/Jooyeshgar/FreeAmir.git
cd FreeAmir
```

**۲. نصب وابستگی‌های PHP:**
```bash
composer install --no-dev --optimize-autoloader
```

**۳. پیکربندی محیط:**
```bash
cp .env.example .env
```
فایل `.env` را ویرایش کنید و مقادیر `DB_HOST`، `DB_DATABASE`، `DB_USERNAME`، `DB_PASSWORD` و `APP_URL` را متناسب با محیط خود تنظیم نمایید.

**۴. تولید کلید برنامه:**
```bash
php artisan key:generate
```

**۵. اجرای Migration پایگاه داده:**
```bash
php artisan migrate
```

**۶. Seed کردن پایگاه داده:**
```bash
php artisan db:seed
```
Seed با داده‌های نمونه (اختیاری):
```bash
php artisan db:seed --class DemoSeeder
```

**۷. بهینه‌سازی کش برنامه:**
```bash
php artisan optimize
```

**۸. نصب وابستگی‌ها و آماده‌سازی فرانت‌اند:**
```bash
npm install
npm run build
```

**۹. وب‌سرور خود را تنظیم کنید** تا به پوشه `/public` اشاره کند و مسیر ریشه (document root) را روی این مسیر قرار دهید. برای اجرای سریع روی سیستم خودتان:
```bash
php artisan serve
```
برنامه در آدرس http://localhost:8000 در دسترس خواهد بود.


## ورود پیش‌فرض

پس از Seed شدن، رمز عبور همه کاربران **`password`** است. حساب‌های کاربری موجود:

| ایمیل | نقش‌ها |
|---|---|
| `admin@example.com` | Super-Admin, Employee |
| `accountant@example.com` | Accountant, Employee |
| `seller@example.com` | Seller, Employee |
| `warehouse@example.com` | Warehousekeeper, Employee |
| `seller-warehouse@example.com` | Seller, Warehousekeeper, Employee |
| `accountant-seller-warehouse@example.com` | Accountant, Seller, Warehousekeeper, Employee |
| `employee@example.com` | Employee |


## مهاجرت پایگاه داده از نسخه قدیمی

برای مهاجرت از نسخه قدیمی مبتنی بر SQLite، به [راهنمای مهاجرت پایگاه داده](https://github.com/Jooyeshgar/FreeAmir/tree/main/script) مراجعه کنید.

</div>

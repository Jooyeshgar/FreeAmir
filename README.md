<div dir="rtl" align="right">

# امیر: نرم‌افزار آزاد حسابداری و ERP ایرانی

**[English version](README.en.md)**

**وضعیت پروژه:** امیر در حال توسعه فعال است و هنوز برای استفاده عملیاتی بدون بررسی و تست مستقل توصیه نمی‌شود. پیش از استفاده در محیط Production، بخش‌های مورد نیاز خود را با داده آزمایشی بررسی کنید.

## معرفی

**امیر** یک پلتفرم متن باز حسابداری و ERP برای کسب‌وکارهای ایرانی است. تمرکز پروژه روی حسابداری دوبل، فاکتور فروش و خرید، مدیریت کالا و بهای تمام‌شده، عملیات سال مالی، حضور و غیاب، حقوق و دستمزد، و انطباق با الزامات مالیاتی ایران از جمله سامانه مودیان است.

### جایگزین نرم‌افزارهای تجاری

امیر به عنوان جایگزینی **رایگان و متن باز** برای نرم‌افزارهای تجاری حسابداری ایرانی طراحی شده است:

- **جایگزین سپیدار** و **جایگزین هلو** با حسابداری دوبل استاندارد
- **ERP متن باز ایرانی** با پشتیبانی از چند شرکت و چند سال مالی
- **نرم افزار حسابداری رایگان** بدون هزینه مجوز یا اشتراک
- **سامانه مودیان متن باز** با اتصال کامل به سامانه مودیات مالیاتی ایران

---

## ویژگی‌های اصلی

| دسته‌بندی | قابلیت‌ها |
|---|---|
| **حسابداری مالی** | حسابداری دوبل، ثبت‌های روزنامه، دفتر کل، ترازنامه، سود و زیان |
| **فروش و خرید** | مشتریان، تأمین‌کنندگان، فاکتور فروش و خرید، برگشت از فروش و خرید |
| **انبارداری** | مدیریت انبارها، انتقال بین انبارها، پیگیری موجودی، بهای تمام‌شده میانگین موزون |
| **منابع انسانی** | کارکنان، حضور و غیاب، حقوق و دستمزد، فیش حقوقی |
| **مالیات** | اتصال به سامانه مودیان، گزارش‌های مالیاتی |
| **مدیریت** | چند شرکت، چند سال مالی، نقش‌ها و دسترسی‌ها |

---

## نصب سریع

### ۱. Docker تک‌دستوری (آزمایش سریع)

```bash
docker run -d --name freeamir -p 80:80 ghcr.io/jooyeshgar/freeamir-all-in-one:latest
```

برنامه پس از اجرا در http://localhost در دسترس است.

### ۲. Docker Compose (محیط عملیاتی)

```bash
mkdir freeamir && cd freeamir
curl -O https://raw.githubusercontent.com/Jooyeshgar/FreeAmir/main/docker/production/docker-compose.prebuilt.yml
curl -O https://raw.githubusercontent.com/Jooyeshgar/FreeAmir/main/docker/production/.env.example
cp docker-compose.prebuilt.yml docker-compose.yml
cp .env.example .env
# فایل .env را ویرایش کنید
docker compose up -d
```

### ۳. نصب استاندارد

```bash
git clone https://github.com/Jooyeshgar/FreeAmir.git
cd FreeAmir
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install && npm run build
php artisan serve
```

> راهنمای کامل نصب در [docs/INSTALLATION.md](docs/INSTALLATION.md) قرار دارد.

### اطلاعات ورود پیش‌فرض

| ایمیل | نقش‌ها |
|---|---|
| `admin@example.com` | مدیر کل |
| `accountant@example.com` | حسابدار |
| `seller@example.com` | فروشنده |
| `warehouse@example.com` | انباردار |

رمز عبور تمام حساب‌ها: `password`

---

## توسعه

### راه‌اندازی با Laravel Sail

```bash
sail up -d
sail artisan test
sail npm run dev
```

### راه‌اندازی بدون Sail

```bash
php artisan test
npm run dev
```

### دستورات مفید

```bash
# اجرای تست‌ها
sail artisan test

# قالب‌بندی کد
./vendor/bin/pint

# اجرای مایگریشن و داده اولیه
sail artisan migrate --seed
```

پیش از تغییر در منطق حسابداری، [راهنمای برنامه‌نویس](docs/developer/README.md) را بخوانید. تغییرات اثرگذار بر محاسبات مالی باید تست داشته باشند.

---

## مستندات

| بخش | فارسی | English |
|---|---|---|
| ویژگی‌ها | [features.html](docs/features.html) | [features.en.html](docs/features.en.html) |
| مقایسه | [comparison.html](docs/comparison.html) | [comparison.en.html](docs/comparison.en.html) |
| سوالات متداول | [faq.html](docs/faq.html) | [faq.en.html](docs/faq.en.html) |
| نقشه راه | [roadmap.html](docs/roadmap.html) | [roadmap.en.html](docs/roadmap.en.html) |
| نماشگاه | [screenshots.html](docs/screenshots.html) | [screenshots.en.html](docs/screenshots.en.html) |
| فهرست مستندات | [docs/README.md](docs/README.md) | [docs/README.en.md](docs/README.en.md) |
| راهنمای نصب | [docs/INSTALLATION.md](docs/INSTALLATION.md) | [docs/INSTALLATION.en.md](docs/INSTALLATION.en.md) |
| راهنمای استفاده‌کنندگان | [docs/user/README.md](docs/user/README.md) | [docs/user/README.en.md](docs/user/README.en.md) |
| مفاهیم حسابداری | [docs/accounting/README.md](docs/accounting/README.md) | [docs/accounting/README.en.md](docs/accounting/README.en.md) |
| راهنمای برنامه‌نویس | [docs/developer/README.md](docs/developer/README.md) | [docs/developer/README.en.md](docs/developer/README.en.md) |

---

## مشارکت

از مشارکت در امیر استقبال می‌کنیم. برای گزارش باگ، پیشنهاد قابلیت و Pull Request، [CONTRIBUTING.md](CONTRIBUTING.md) را ببینید.

### انواع مشارکت

- **گزارش باگ:** از قسمت Issues گیت‌هاب استفاده کنید
- **پیشنهاد ویژگی:** ابتدا یک Issue با برچسب feature request ایجاد کنید
- **ارسال PR:** فورک، branch جدید، تست و ارسال Pull Request

---

## نقشه راه

- [x] حسابداری دوبل
- [x] فاکتور فروش و خرید
- [x] انبارداری و بهای تمام‌شده
- [x] حضور و غیاب
- [x] حقوق و دستمزد
- [x] چند شرکت
- [x] چند سال مالی
- [x] اتصال کامل سامانه مودیان
- [ ] نقش‌های اختصاصی شرکت
- [ ] فرآیندهای تأیید
- [ ] گزارش‌های حسابرسی
- [ ] گسترش API
- [ ] پشتیبانی موبایل / PWA

---

## سوالات متداول

**آیا امیر واقعاً رایگان است؟**
بله. امیر تحت مجوز GPL-3 منتشر شده و بدون هزینه مجوز یا اشتراک است.

**آیا می‌توانم آن را روی سرور خودم میزبانی کنم؟**
بله. امیر برای خودمیزبانی طراحی شده و داده‌ها کاملاً در اختیار شماست.

**آیا از سامانه مودیان پشتیبانی می‌کند؟**
بله. امیر از سامانه مودیان پشتیبانی می‌کند. هر شرکت می‌تواند گواهی و کلید خصوصی خود را تنظیم کند. [راهنمای راه‌اندازی مودیان](docs/moadian.html)

**آیا از چند شرکت پشتیبانی می‌کند؟**
بله. امیر از چند شرکت با سال‌های مالی مستقل پشتیبانی می‌کند.

**آیا از حقوق و دستمزد پشتیبانی می‌کند؟**
بله. محاسبه حقوق، حضور و غیاب، فیش حقوقی و گزارش‌ها شامل می‌شود.

**آیا آماده استفاده عملیاتی است؟**
امیر در حال توسعه فعال است. پیش از استفاده عملیاتی، با داده آزمایشی بررسی کنید.

---

## مجوز

این پروژه تحت مجوز GPL-3 منتشر شده است. جزئیات در [LICENSE](LICENSE) قرار دارد.

</div>

# امیر: نرم‌افزار آزاد حسابداری لاراول

**[English version](README.en.md)**

**وضعیت پروژه:** امیر در حال توسعه فعال است و هنوز برای استفاده عملیاتی بدون بررسی و تست مستقل توصیه نمی‌شود. پیش از استفاده در محیط Production، بخش‌های مورد نیاز خود را با داده آزمایشی بررسی کنید.

## معرفی

**امیر** یک نرم‌افزار حسابداری آزاد و رایگان مبتنی بر Laravel است که برای کسب‌وکارهای ایرانی طراحی شده است. تمرکز پروژه روی حسابداری دوبل، فاکتور فروش و خرید، مدیریت کالا و بهای تمام‌شده، عملیات سال مالی، و تجربه فارسی‌اول است.

## قابلیت‌ها

**ویژگی ها:**

*   **رابط کاربری بصری:** استفاده آسان برای کسب و کارها با هر اندازه و دانش فنی.
*   **چند زبانه:** در حال حاضر از فارسی پشتیبانی می‌کند (با قابلیت اضافه شدن زبان‌های دیگر در آینده).
*   **کارکردهای حسابداری:**
    *   مدیریت درآمد و هزینه
    *   پیگیری فاکتورها و رسیدها
    *   تهیه گزارشات مالی
    *   پشتیبانی از قوانین مالیاتی ایران
    *   (سامانه مودیان - در حال توسعه)
*   **انبارداری:**
    *   مدیریت محصولات و موجودی کالا
    *   پیگیری سطح موجودی و گردش انبار
    *   محاسبه بهای تمام‌شده به روش میانگین موزون متحرک (سیستم دائمی)
*   **حضور و غیاب:**
    *   ثبت ورود و خروج پرسنل
    *   پیگیری حضور روزانه و ماهانه
    *   وارد کردن اطلاعات حضور و غیاب از منابع خارجی
*   **حقوق و دستمزد:**
    *   محاسبه حقوق ماهانه بر اساس حضور و غیاب
    *   مدیریت کسورات، پاداش‌ها و مزایا
    *   تهیه فیش حقوقی و گزارشات حقوق و دستمزد
*   **آزاد (متن باز):** استفاده، اصلاح و مشارکت در آن رایگان است.

## نصب سریع

راهنمای کامل نصب در [docs/INSTALLATION.md](docs/INSTALLATION.md) قرار دارد و سه مسیر را پوشش می‌دهد:

- Docker Compose برای محیط عملیاتی
- Docker تک‌دستوری برای آزمایش سریع
- نصب استاندارد با PHP، Composer، npm و MariaDB

پس از نصب، برنامه معمولاً از `http://localhost` در دسترس است. اطلاعات ورود پیش‌فرض در محیط‌های آزمایشی:

- ایمیل: `admin@example.com`
- رمز عبور: `password`

## مستندات

همه مستندات پروژه فایل Markdown هستند. نسخه فارسی هر README بدون پیشوند است و نسخه انگلیسی همان README با الگوی `README.en.md` نگهداری می‌شود.

| بخش | فارسی | English |
|---|---|---|
| فهرست کامل مستندات | [docs/README.md](docs/README.md) | [docs/README.en.md](docs/README.en.md) |
| راهنمای استفاده کنندگان | [docs/user/README.md](docs/user/README.md) | [docs/user/README.en.md](docs/user/README.en.md) |
| مفاهیم حسابداری | [docs/accounting/README.md](docs/accounting/README.md) | [docs/accounting/README.en.md](docs/accounting/README.en.md) |
| راهنمای برنامه‌نویس | [docs/developer/README.md](docs/developer/README.md) | [docs/developer/README.en.md](docs/developer/README.en.md) |
| حضور و غیاب | [docs/user/attendance/README.md](docs/user/attendance/README.md) | [docs/user/attendance/README.en.md](docs/user/attendance/README.en.md) |
| حقوق و دستمزد | [docs/user/salary/README.md](docs/user/salary/README.md) | [docs/user/salary/README.en.md](docs/user/salary/README.en.md) |
| نصب | [docs/INSTALLATION.md](docs/INSTALLATION.md) | [docs/INSTALLATION.en.md](docs/INSTALLATION.en.md) |
| سال مالی چیست و چگونه ساخته می‌شود | [docs/fiscal-year.md](docs/fiscal-year.md) | [docs/fiscal-year.en.md](docs/fiscal-year.en.md) |
| خروجی/ورودی سال مالی | [docs/FiscalYearExportImport.md](docs/FiscalYearExportImport.md) | فعلاً همان سند انگلیسی |

## توسعه

برای توسعه محلی می‌توانید از Laravel Sail یا دستورهای مستقیم استفاده کنید:

```bash
sail up -d
sail artisan test
sail npm run dev
```

در صورت نبود Sail:

```bash
php artisan test
npm run dev
```

پیش از تغییر در منطق حسابداری، [راهنمای برنامه‌نویس](docs/developer/README.md) را بخوانید. تغییرات اثرگذار بر محاسبات مالی باید تست داشته باشند.

## مشارکت

از مشارکت در امیر استقبال می‌کنیم. برای گزارش باگ، پیشنهاد قابلیت و Pull Request، [CONTRIBUTING.md](CONTRIBUTING.md) را ببینید.

## مجوز

این پروژه تحت مجوز GPL-3 منتشر شده است. جزئیات در [LICENSE](LICENSE) قرار دارد.

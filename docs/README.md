# فهرست مستندات امیر

**[English version](README.en.md)**

این پوشه مرکز مستندات Markdown پروژه امیر است. برای هماهنگی زبان‌ها، README فارسی بدون پیشوند نگهداری می‌شود و نسخه انگلیسی همان فایل با نام `README.en.md` قرار می‌گیرد.

## مسیرهای اصلی

| مسیر | مخاطب | توضیح |
|---|---|---|
| [راهنمای استفاده کنندگان](user/README.md) | کاربران غیرتوسعه‌دهنده | عملیات روزمره، حضور و غیاب، حقوق و دستمزد، موجودی کالا و سال مالی |
| [مفاهیم حسابداری](accounting/README.md) | کاربران و توسعه‌دهندگان | مفاهیم حسابداری، بهای تمام‌شده، برگشت از خرید/فروش و سال مالی |
| [راهنمای برنامه‌نویس](developer/README.md) | توسعه‌دهندگان | معماری پروژه، دیتابیس، تست و اسکریپت‌ها |
| [راهنمای نصب](INSTALLATION.md) | مدیر سیستم و توسعه‌دهنده | نصب با Docker Compose، Docker تک‌دستوری یا نصب استاندارد |
| [سال مالی](fiscal-year.md) | همه کاربران | تعریف سال مالی و راهنمای ایجاد آن در امیر |
| [خروجی/ورودی سال مالی](FiscalYearExportImport.md) | مدیر سیستم و توسعه‌دهنده | دستورهای `fiscal-year:export` و `fiscal-year:import` |

## همه فایل‌های مستندات

| فایل | دسته | توضیح |
|---|---|---|
| [INSTALLATION.md](INSTALLATION.md) / [INSTALLATION.en.md](INSTALLATION.en.md) | نصب | راهنمای نصب فارسی و انگلیسی |
| [user/README.md](user/README.md) / [user/README.en.md](user/README.en.md) | کاربر عادی | فهرست ثانویه مستندات کاربردی و حسابداری |
| [accounting/README.md](accounting/README.md) / [accounting/README.en.md](accounting/README.en.md) | حسابداری | فهرست مفاهیم حسابداری |
| [developer/README.md](developer/README.md) / [developer/README.en.md](developer/README.en.md) | برنامه‌نویس | فهرست ثانویه مستندات فنی |
| [user/attendance/README.md](user/attendance/README.md) / [user/attendance/README.en.md](user/attendance/README.en.md) | حضور و غیاب | راهنمای کار با شیفت، لاگ و کارکرد ماهانه |
| [user/salary/README.md](user/salary/README.md) / [user/salary/README.en.md](user/salary/README.en.md) | حقوق و دستمزد | راهنمای عوامل حقوقی، احکام و فیش حقوقی |
| [user/inventory-costing.md](user/inventory-costing.md) / [user/inventory-costing.en.md](user/inventory-costing.en.md) | حسابداری کالا | بهای تمام‌شده، روش‌های محاسبه و روش مورد استفاده امیر |
| [fiscal-year.md](fiscal-year.md) / [fiscal-year.en.md](fiscal-year.en.md) | سال مالی | مفهوم سال مالی و ایجاد آن |
| [FiscalYearExportImport.md](FiscalYearExportImport.md) | سال مالی | خروجی گرفتن و وارد کردن داده سال مالی |
| [accounting-basics.md](accounting-basics.md) | حسابداری | مبانی حسابداری، بدهکار، بستانکار و سند |
| [return-sell-return-buy.md](return-sell-return-buy.md) | حسابداری کالا | برگشت از فروش و برگشت از خرید |
| [project-structure.md](project-structure.md) | فنی | ساختار پروژه Laravel |
| [database-guide.md](database-guide.md) | فنی | ساختار پایگاه داده و روابط |
| [testing-guide.md](testing-guide.md) | فنی | راهنمای تست‌نویسی و اجرای تست |
| [../script/README.md](../script/README.md) | ابزارها | مستندات اسکریپت‌های مهاجرت و داده |

## نگهداری مستندات

- هر README فارسی باید با README انگلیسی متناظر از نظر ساختار و لینک‌ها همگام بماند.
- اگر فایل جدیدی به `docs/` یا پوشه‌های زیرمجموعه اضافه شد، آن را در این فهرست یا یکی از دو فهرست ثانویه ثبت کنید.
- اگر مستندی برای کاربران عادی قابل استفاده است، آن را در [user/README.md](user/README.md) هم معرفی کنید.
- اگر مستندی برای توسعه یا ساختار داخلی پروژه است، آن را در [developer/README.md](developer/README.md) هم معرفی کنید.

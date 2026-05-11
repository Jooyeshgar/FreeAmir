# راهنمای برنامه‌نویس امیر

**[English version](README.en.md)**  
**[بازگشت به فهرست مستندات](../README.md)**

این فهرست برای توسعه‌دهندگان و مشارکت‌کنندگان پروژه است. اگر قرار است کد Laravel، پایگاه داده، تست‌ها، سرویس‌ها یا منطق حسابداری را تغییر دهید، از اینجا شروع کنید.

## مسیر پیشنهادی مطالعه

1. [ساختار پروژه](../project-structure.md)
2. [راهنمای دیتابیس](../database-guide.md)
3. [راهنمای تست](../testing-guide.md)
4. [مبانی حسابداری](../accounting-basics.md)
5. [سال مالی](../fiscal-year.md)
6. [خروجی/ورودی سال مالی](../FiscalYearExportImport.md)

## مستندات فنی

| فایل | کاربرد |
|---|---|
| [project-structure.md](../project-structure.md) | معماری Laravel، پوشه‌ها و الگوی کلی کد |
| [database-guide.md](../database-guide.md) | ساختار جدول‌ها، روابط و نکات کار با دیتابیس |
| [testing-guide.md](../testing-guide.md) | اجرای تست‌ها و نوشتن تست‌های Feature و Unit |
| [../script/README.md](../../script/README.md) | ابزارها و اسکریپت‌های مهاجرت داده |

## مستندات دامنه حسابداری برای توسعه

| فایل | کاربرد |
|---|---|
| [accounting-basics.md](../accounting-basics.md) | مفاهیم بدهکار/بستانکار، سند متوازن و مدل ذخیره تراکنش‌ها |
| [user/inventory-costing.md](../user/inventory-costing.md) | توضیح کاربرمحور بهای تمام‌شده و روش میانگین موزون متحرک |
| [return-sell-return-buy.md](../return-sell-return-buy.md) | ثبت برگشت از فروش و برگشت از خرید |
| [fiscal-year.md](../fiscal-year.md) | مفهوم سال مالی و ایجاد آن در سیستم |
| [FiscalYearExportImport.md](../FiscalYearExportImport.md) | دستورهای خروجی و ورودی سال مالی |

## قوانین مهم برای تغییر کد

- موازنه اسناد حسابداری را نقض نکنید.
- داده‌های شرکت‌ها و سال‌های مالی باید جدا بمانند.
- تغییرات مالی باید تست Feature یا Unit داشته باشند.
- کنترلرها را سبک نگه دارید و منطق را در سرویس‌ها قرار دهید.
- پیش از تغییر در transaction builderها یا محاسبه بهای تمام‌شده، بازبینی دامنه حسابداری لازم است.

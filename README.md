## امیر: نرم افزار آزاد حسابداری
**[English Version](README.en.md)**


**توجه مهم:** امیر در حال حاضر در مرحله **توسعه** است و هنوز برای استفاده در محیط عملیاتی آماده نیست. ما بطور فعالانه در حال توسعه آن هستیم و به زودی تاریخ انتشار رسمی را اعلام خواهیم کرد. با ما همراه باشید!

### معرفی:

**امیر** یک نرم افزار حسابداری رایگان و آزاد است که با لاراول نوشته شده و به طور خاص برای کسب و کارها و افراد ایرانی طراحی شده است. این نرم افزار با هدف ارائه یک راهکار جامع و کاربرپسند برای مدیریت امور مالی، با ویژگی هایی که مطابق با نیازهای خاص کاربران ایرانی است، از جمله پشتیبانی از قوانین مالیاتی ایران و سامانه مودیان ساخته شده است.

**ویژگی ها:**

* **آزاد (متن باز):** استفاده، اصلاح و مشارکت در آن رایگان است و به آزادی های کاربران احترام می گذارد.
* **رابط کاربری کاربرپسند:** استفاده آسان برای کسب و کارها با هر اندازه و دانش فنی.
* **چند زبانه:** در حال حاضر از زبان فارسی و انگلسیس پشتیبانی می کند (با قابلیت اضافه شدن زبان های دیگر در آینده).
* **کارکردهای حسابداری:**
    * سامانه مودیان
    * مدیریت درآمد و هزینه
    * پیگیری فاکتورها و رسیدها
    * تهیه گزارشات مالی
    * پشتیبانی از قوانین مالیاتی ایران

### نصب:

1. **پیش نیازها:**
    * PHP >= 8.1
    * Composer
    * MySQL database
    * Node JS >= 18.0.0
2. **دریافت فایل ها:**

```bash
git clone https://github.com/Jooyeshgar/FreeAmir.git
```

3. **نصب وابستگی ها:**

```bash
composer install
```

4. **فایل .env.example را به .env کپی کرده و اطلاعات مربوط به پایگاه داده را تنظیم کنید:**

```bash
cp .env.example .env
```

5. **ساخت کلید:**

```bash
php artisan key:generate
```

6. **ساخت جداول پایگاه داده:**

```bash
php artisan migrate
```

7. **ورود داده نمونه در پایگاه داده:**

```bash
php artisan db:seed
```

داده های نمایشی (اختیاری)
```bash
php artisan db:seed --class DemoSeeder
```

8. **نصب بسته‌های npm:**

```bash
npm install
```

9. **اجرای وایت**

```bash
npm run dev
```

10. **اجرای سرور:**

```bash
php artisan serve
```

### استفاده:

1. با مرورگر وب خود به برنامه در http://localhost:8000 (یا پورتی که در فایل .env شما مشخص شده است) دسترسی پیدا کنید.
2. با اعتبار پیش فرض وارد شوید (ایمیل: admin@example.com، رمز عبور: password).
3. ویژگی ها و کارکردهای برنامه را بررسی کنید.

### مشارکت:

ما از مشارکت در پروژه امیر استقبال می کنیم! لطفاً برای دستورالعمل های مربوط به ارسال گزارش باگ، درخواست ویژگی و درخواست های pull به فایل CONTRIBUTING.md مراجعه کنید: CONTRIBUTING.md

### لایسنس:

این پروژه تحت لایسنس GPL-3 منتشر شده است. برای جزئیات به فایل LICENSE: LICENSE مراجعه کنید.

### پشتیبانی:

برای هر گونه سوال یا مشکلی، لطفاً در مخزن گیت هاب یک issue ایجاد کنید.
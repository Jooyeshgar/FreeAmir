<div dir="rtl">

# اتصال فرانت بسته‌ها

**[English version](package-frontend.en.md)**

**[بازگشت به راهنمای برنامه‌نویس](README.md)**

بسته‌های اختیاری، صفحه‌های Laravel Blade خود را داخل امیر نمایش می‌دهند.
میزبان layout، کامپوننت‌های مشترک، رندر منوی ترجمه‌شده، build مربوط به Tailwind/daisyUI و محیط Alpine را فراهم می‌کند.
اولین مصرف‌کنندهٔ این سازوکار `amir/invoice-categories` است.

## صفحه‌های متعلق به بسته

viewهای بسته را در `resources/views/` و ترجمه‌ها را در `lang/fa/` و `lang/en/` نگه دارید.
آن‌ها را همراه مسیرها در service provider بسته ثبت کنید:

```php
$this->loadViewsFrom(__DIR__.'/../resources/views', 'example');
$this->loadTranslationsFrom(__DIR__.'/../lang', 'example');
$this->loadRoutesFrom(__DIR__.'/../routes/web.php');
```

view را با فضای نام، مانند `view('example::index', $data)`، رندر کنید و از کامپوننت‌های میزبان استفاده کنید:

```blade
<x-app-layout :title="__('example::messages.title')">
    <x-show-message-bags />
    <form method="POST" action="{{ route('example.store') }}">
        @csrf
        <x-input id="name" name="name" :title="__('Name')" required
            :value="old('name')" />
        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
    </form>
</x-app-layout>
```

کامپوننت‌های میزبان شامل `x-input`، `x-textarea`، `x-button` و `x-show-message-bags` هستند.
متن‌ها را ترجمه کنید؛ خروجی Blade را escape کنید و از کنترل‌های معنایی، چیدمان واکنش‌گرا و توکن‌های پوسته
مانند `bg-base-100` و `text-base-content` استفاده کنید.
نام کلاس‌های Tailwind را کامل بنویسید؛ ساختن نام کلاس با اتصال رشته‌ها در زمان اجرا، هنگام build قابل تشخیص مطمئن نیست.
رنگ پویا را با مقدار hexadecimal اعتبارسنجی‌شده در style قرار دهید؛ نام کلاس دلخواه تولید نکنید.
Alpine را میزبان بارگذاری می‌کند؛ آن را دوباره بارگذاری نکنید.

## منوی مدیریت

بسته پس از ثبت مسیرهای خود، آیتم منو را در میزبان ثبت می‌کند:

```php
$this->app->make(\App\Extensions\NavigationRegistry::class)->register('example.categories', [
    'area' => 'navigation.management',
    'label' => 'example::messages.title',
    'route' => 'example.index',
    'permission' => 'access-super-admin-panel',
    'priority' => 100,
    'visible' => static fn (array $context): bool => $context['fiscal_year_id'] !== null,
]);
```

شناسه‌ها در کل رجیستری یکتا هستند. اولویت کوچک‌تر زودتر نمایش داده می‌شود و اولویت برابر با شناسه مرتب می‌شود.
میزبان `navigation.management` را با `x-extension-navigation` در منوی مشترک دسکتاپ و موبایل نمایش می‌دهد.
کامپوننت در هر رندر احراز هویت، وجود named route، مجوز و callback نمایش را بررسی می‌کند.
عنوان ترجمه‌شده escape می‌شود و مسیر جاری با `aria-current="page"` مشخص می‌شود.

قابلیت‌های فاکتور می‌توانند در `navigation.workspace.invoices` ثبت شوند تا در منوی فاکتورها قرار بگیرند.
ناحیه فقط محل نمایش را تعیین می‌کند؛ مجوز آیتم و مجوز endpoint همچنان باید بررسی شوند.
بستهٔ دسته‌بندی فاکتور از همین ناحیه استفاده می‌کند و مجوز مدیریت فعلی صفحهٔ CRUD را حفظ می‌کند.

callback مقادیر `user` و `fiscal_year_id` را دریافت می‌کند.
درخواست یا کاربر زمان boot را نگه ندارید. نمایش منو فقط مربوط به رابط است؛ هر endpoint را جداگانه با middleware،
مجوز Form Request و context سال مالی میزبان محافظت کنید. پنهان‌کردن منو جایگزین بررسی مجوز درخواست نیست.

## فایل‌های فرانت و توسعهٔ محلی

امیر از Tailwind نسخهٔ ۴ و daisyUI نسخهٔ ۵ استفاده می‌کند.
فایل `resources/css/app.css` مسیر `vendor/amir` را صریحاً اسکن می‌کند و پیکربندی Vite، ضمن حفظ مسیرهای
پیش‌فرض refresh لاراول، viewهای Blade بسته را هم اضافه می‌کند.
پیش از build فرانت، بسته‌های Composer را نصب کنید. مسیر نصب در symlink محلی Composer و آرشیو انتشار یکی است؛
build محیط عملیاتی به checkout بسته در پوشهٔ همسایه نیاز ندارد.

```bash
./vendor/bin/sail npm run dev
./vendor/bin/sail npm run build
```

برای توسعهٔ بستهٔ symlinkشده در Sail، از Compose override محلی برای mount کردن checkout بسته استفاده کنید.
پس از نصب یا حذف بسته دوباره build بگیرید تا خروجی با viewهای نصب‌شده هماهنگ شود.
وابستگی جدید، اسکریپت مستقل یا bundle اختصاصی به قرارداد اتصال جداگانه نیاز دارد؛ این مرحله از bundle مشترک فعلی استفاده می‌کند.

## اعتبارسنجی و بررسی

کنترلرها را سبک نگه دارید. ورودی را پیش از بررسی یکتایی یکسان‌سازی کنید، اعتبارسنجی را با Form Request انجام دهید و
خواندن و نوشتن را با `HostContext` به سال مالی انتخاب‌شده محدود کنید.
ابتدا تعلق رکورد به سال مالی را بررسی کنید و سپس بسته‌بودن سال را بسنجید تا اطلاعات رکورد سال دیگر افشا نشود.
برای نوشتن و فرم‌های ایجاد و ویرایش، `ensureFiscalYearOpen()` را فراخوانی کنید.
ورودی قبلی فرم و مقادیر ذخیره‌شده را escape کنید.

مجوز endpoint را مستقل از نمایش منو تست کنید. مهمان، کاربر بدون مجوز، نبود سال مالی، شناسهٔ سال دیگر شامل سال بسته،
خطای اعتبارسنجی و CRUD را پوشش دهید. حذف دسته‌بندی باید فقط تخصیص‌ها را حذف کند و اطلاعات فاکتور را نگه دارد.
تست‌های اتصال بستهٔ دسته‌بندی به میزبان واقعی، در مخزن خود بسته و مسیر `integration/freeamir/` هستند.

تست‌های میزبان برای نبود بستهٔ اختیاری و منو را اجرا کنید:

```bash
./vendor/bin/sail artisan test tests/Feature/Extensions
```

با بسته و بدون بسته build بگیرید و صفحه را در مرورگر از نظر فارسی راست‌به‌چپ، انگلیسی چپ‌به‌راست، عرض کم،
نمایش منو، ارسال فرم و پوستهٔ روشن و تیره بررسی کنید.

## جایگاه‌های افزونه

میزبان فعلی اسلات‌های فرم، جزئیات و فیلتر فاکتور را نمایش می‌دهد.
provider بسته باید view مورد اعتماد را ثبت کند و همهٔ داده‌های ارسالی را با `InvoiceExtensionRegistry` اعتبارسنجی کند.

</div>

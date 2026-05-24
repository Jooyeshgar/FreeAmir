<?php

namespace App\Faker;

use Faker\Provider\Base;

/**
 * Custom Faker provider for Persian service names.
 *
 * Registering (AppServiceProvider::register):
 *   $faker->addProvider(new PersianServiceProvider($faker));
 *
 * Usage inside a factory:
 *   $this->faker->persianServiceName()
 *   $this->faker->persianServiceNameFromCategory('it')
 *   $this->faker->persianServiceCategory()
 *   $this->faker->persianServiceGroupName()
 */
class PersianServiceProvider extends Base
{
    // ------------------------------------------------------------------ //
    //  Data
    // ------------------------------------------------------------------ //

    protected static array $categories = [
        'it'            => 'خدمات فناوری اطلاعات',
        'consulting'    => 'مشاوره',
        'financial'     => 'خدمات مالی و حسابداری',
        'transportation' => 'حمل‌ونقل و لجستیک',
        'maintenance'   => 'تعمیر و نگهداری',
        'cleaning'      => 'نظافت و بهداشت',
        'education'     => 'آموزش و پرورش',
        'printing'      => 'چاپ و تبلیغات',
        'security'      => 'امنیت و حراست',
        'catering'      => 'پذیرایی و تغذیه',
    ];

    protected static array $servicesByCategory = [
        'it' => [
            'طراحی وب‌سایت', 'توسعه اپلیکیشن موبایل', 'پشتیبانی نرم‌افزار', 'نگهداری شبکه',
            'راه‌اندازی سرور', 'امنیت سایبری', 'پشتیبان‌گیری داده', 'مدیریت ابری',
            'طراحی پایگاه داده', 'بهینه‌سازی سئو', 'راه‌اندازی ایمیل سازمانی',
            'پیاده‌سازی ERP', 'آموزش نرم‌افزار', 'ریکاوری داده', 'نصب و راه‌اندازی سخت‌افزار',
        ],
        'consulting' => [
            'مشاوره مدیریت', 'مشاوره حقوقی', 'مشاوره مالیاتی', 'مشاوره سرمایه‌گذاری',
            'مشاوره منابع انسانی', 'مشاوره بازاریابی', 'مشاوره صادرات', 'مشاوره کسب‌وکار',
            'مشاوره بیمه', 'مشاوره بهره‌وری', 'مشاوره برندینگ', 'مشاوره استراتژی',
        ],
        'financial' => [
            'حسابداری شرکت', 'حسابرسی داخلی', 'تهیه اظهارنامه مالیاتی', 'مدیریت حقوق و دستمزد',
            'تهیه صورت‌های مالی', 'ارزیابی دارایی', 'مشاوره مالی', 'تامین مالی پروژه',
            'خدمات بیمه', 'مدیریت نقدینگی', 'تهیه بودجه سالانه', 'حسابداری مالیاتی',
        ],
        'transportation' => [
            'حمل‌ونقل بار درون‌شهری', 'حمل‌ونقل بار برون‌شهری', 'پیک موتوری', 'باربری اثاثیه',
            'ترانزیت کالا', 'توزیع محصول', 'حمل‌ونقل یخچالی', 'انبارداری کالا',
            'خدمات گمرکی', 'حمل بار هوایی', 'ارسال بین‌المللی', 'لجستیک معکوس',
        ],
        'maintenance' => [
            'تعمیر دستگاه‌های اداری', 'سرویس کولر و تهویه', 'لوله‌کشی و تأسیسات',
            'برق‌کاری ساختمان', 'نقاشی ساختمان', 'تعمیر آسانسور', 'سرویس ژنراتور',
            'تعمیر سیستم اعلام حریق', 'نگهداری فضای سبز', 'تعمیر و نگهداری سرمایش',
            'سرویس آب‌گرمکن', 'تعمیرات عمومی ساختمان',
        ],
        'cleaning' => [
            'نظافت اداری روزانه', 'نظافت صنعتی', 'شست‌وشوی نما', 'نظافت پس از ساخت',
            'ضدعفونی محیط', 'نظافت انبار', 'سرویس بهداشتی محیط', 'شست‌وشوی موکت',
            'نظافت مجتمع مسکونی', 'شست‌وشوی پنجره‌های مرتفع', 'خدمات زباله‌برداری',
        ],
        'education' => [
            'آموزش زبان انگلیسی', 'آموزش مهارت‌های مدیریتی', 'دوره حسابداری', 'آموزش نرم‌افزارهای اداری',
            'کارگاه فن بیان', 'دوره ISO', 'آموزش ایمنی و بهداشت', 'دوره بازاریابی دیجیتال',
            'آموزش کارمندان', 'دوره رهبری سازمانی', 'آموزش برنامه‌نویسی', 'کلاس طراحی گرافیک',
        ],
        'printing' => [
            'چاپ کارت ویزیت', 'چاپ بنر تبلیغاتی', 'طراحی گرافیک', 'چاپ کاتالوگ',
            'صحافی و جلدسازی', 'چاپ سربرگ', 'چاپ پاکت نامه', 'چاپ تقویم',
            'طراحی لوگو', 'چاپ فاکتور', 'تبلیغات دیجیتال', 'طراحی پوستر',
        ],
        'security' => [
            'نگهبانی و حراست', 'نصب دوربین مداربسته', 'سیستم اعلام سرقت', 'کنترل تردد',
            'سیستم اعلام حریق', 'نگهبانی شبانه', 'امنیت رویداد', 'حفاظت فیزیکی',
            'مانیتورینگ از راه دور', 'دربان هوشمند', 'ایمنی و آتش‌نشانی',
        ],
        'catering' => [
            'تهیه غذای سازمانی', 'پذیرایی جلسات', 'کترینگ مراسم', 'سرویس چای و قهوه',
            'بوفه اداری', 'پخت غذای روزانه', 'ارائه میان‌وعده', 'پذیرایی همایش',
            'سرویس میوه و شیرینی', 'تأمین آب آشامیدنی', 'غذای رژیمی سازمانی',
        ],
    ];

    protected static array $groupAdjectives = [
        'تخصصی', 'عمومی', 'ویژه', 'سازمانی', 'حرفه‌ای', 'مدیریتی', 'پایه', 'پیشرفته',
    ];

    // ------------------------------------------------------------------ //
    //  Public API
    // ------------------------------------------------------------------ //

    /**
     * Return a random Persian service name (from any category).
     */
    public function persianServiceName(): string
    {
        $categoryKey = static::randomKey(static::$servicesByCategory);

        return static::randomElement(static::$servicesByCategory[$categoryKey]);
    }

    /**
     * Return a random Persian service name from a given category key.
     * Valid keys: it, consulting, financial, transportation, maintenance,
     *             cleaning, education, printing, security, catering.
     */
    public function persianServiceNameFromCategory(string $category): string
    {
        $services = static::$servicesByCategory[$category]
            ?? throw new \InvalidArgumentException("Unknown service category: \"$category\"");

        return static::randomElement($services);
    }

    /**
     * Return the Persian label for a random service category.
     * Example: "خدمات فناوری اطلاعات"
     */
    public function persianServiceCategory(): string
    {
        return static::randomElement(array_values(static::$categories));
    }

    /**
     * Return a realistic Persian service group name.
     * Example: "خدمات تخصصی فناوری اطلاعات"
     */
    public function persianServiceGroupName(): string
    {
        $category  = static::randomElement(array_values(static::$categories));
        $adjective = static::randomElement(static::$groupAdjectives);

        return "خدمات $adjective $category";
    }

    /**
     * Return all available category keys.
     *
     * @return string[]
     */
    public static function serviceCategoryKeys(): array
    {
        return array_keys(static::$categories);
    }
}

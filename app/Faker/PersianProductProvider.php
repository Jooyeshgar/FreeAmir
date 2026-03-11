<?php

namespace App\Faker;

use Faker\Provider\Base;

/**
 * Custom Faker provider for Persian product names.
 *
 * Registering (AppServiceProvider::boot):
 *   $this->app->extend('faker', fn ($faker) => tap($faker, fn ($f) => $f->addProvider(new PersianProductProvider($f))));
 *
 * Usage inside a factory:
 *   $this->faker->persianProductName()
 *   $this->faker->persianProductNameFromCategory('electronics')
 *   $this->faker->persianProductCategory()
 */
class PersianProductProvider extends Base
{
    // ------------------------------------------------------------------ //
    //  Data
    // ------------------------------------------------------------------ //

    protected static array $categories = [
        'electronics'   => 'الکترونیک',
        'office'        => 'لوازم اداری',
        'food'          => 'مواد غذایی',
        'clothing'      => 'پوشاک',
        'appliances'    => 'لوازم خانگی',
        'building'      => 'مصالح ساختمانی',
        'hygiene'       => 'بهداشتی',
        'stationery'    => 'نوشت‌افزار',
        'furniture'     => 'مبلمان',
        'tools'         => 'ابزارآلات',
    ];

    protected static array $productsByCategory = [
        'electronics' => [
            'لپ‌تاپ', 'تلفن همراه', 'تبلت', 'کیبورد', 'ماوس', 'مانیتور', 'پرینتر', 'اسکنر',
            'هدفون', 'اسپیکر', 'وب‌کم', 'فلش درایو', 'هارد اکسترنال', 'روتر بی‌سیم', 'شارژر',
            'کابل HDMI', 'پاوربانک', 'کارت حافظه', 'دوربین دیجیتال', 'پروژکتور',
        ],
        'office' => [
            'میز اداری', 'صندلی اداری', 'کمد بایگانی', 'تابلو وایت‌بورد', 'تلفن رومیزی',
            'دستگاه فکس', 'ماشین حساب', 'منگنه', 'سوزن منگنه', 'پانچ', 'زونکن', 'پوشه',
            'کلاسور', 'کازیه', 'سینی مدارک', 'نگهدارنده کاغذ', 'مهر', 'جوهر مهر',
        ],
        'food' => [
            'برنج ایرانی', 'روغن نباتی', 'شکر', 'نمک', 'چای ایرانی', 'قهوه فوری',
            'کنسرو ماهی تن', 'لبنیات پاستوریزه', 'آرد گندم', 'ماکارونی', 'عدس',
            'نخود', 'لوبیا', 'رب گوجه‌فرنگی', 'روغن زیتون', 'سرکه', 'آبلیمو',
            'زعفران', 'زردچوبه', 'دارچین',
        ],
        'clothing' => [
            'پیراهن مردانه', 'پیراهن زنانه', 'شلوار جین', 'کت‌وشلوار', 'مانتو', 'کفش چرم',
            'کفش ورزشی', 'دمپایی', 'جوراب', 'کراوات', 'کیف چرم', 'کمربند', 'عینک آفتابی',
            'شال', 'کلاه زمستانی', 'دستکش', 'ژاکت بافتنی', 'پالتو',
        ],
        'appliances' => [
            'یخچال‌فریزر', 'ماشین لباسشویی', 'جاروبرقی', 'اتو', 'بخاری گازی',
            'کولر آبی', 'دستگاه تصفیه هوا', 'آب‌سردکن', 'چرخ گوشت', 'مخلوط‌کن',
            'توستر', 'کتری برقی', 'سماور برقی', 'آبمیوه‌گیر', 'ساندویچ‌ساز',
            'چای‌ساز', 'اجاق گاز', 'هود آشپزخانه',
        ],
        'building' => [
            'آجر نسوز', 'سیمان پرتلند', 'میلگرد', 'تیرآهن', 'لوله آهنی', 'کابل برق',
            'کاشی سرامیک', 'موزاییک', 'رنگ ساختمانی', 'عایق رطوبتی', 'یونولیت',
            'پروفیل آلومینیوم', 'شیشه دوجداره', 'بتون آماده', 'ماسه بادی', 'گچ',
        ],
        'hygiene' => [
            'شامپو', 'خمیر دندان', 'صابون', 'مایع ظرفشویی', 'مایع دستشویی',
            'اسپری خوشبوکننده', 'ضدعفونی‌کننده', 'دستمال توالت', 'دستمال کاغذی',
            'پنبه هیدروفیل', 'باند زخم', 'مسواک', 'نخ دندان', 'لوسیون بدن',
            'کرم مرطوب‌کننده', 'ژل دوش',
        ],
        'stationery' => [
            'خودکار بیک', 'مداد مشکی', 'مداد رنگی', 'ماژیک وایت‌بورد', 'ماژیک دائمی',
            'دفتر مشق', 'دفتر طراحی', 'کاغذ A4', 'پاک‌کن', 'تراش', 'خط‌کش', 'پرگار',
            'گیره کاغذ', 'چسب مایع', 'چسب نواری', 'نوار تصحیح',
        ],
        'furniture' => [
            'مبل تخت‌خواب‌شو', 'کاناپه', 'میز غذاخوری', 'صندلی چوبی', 'تخت‌خواب یک‌نفره',
            'تخت‌خواب دونفره', 'کمد لباس', 'میز توالت', 'قفسه کتاب', 'میز مطالعه',
            'کنسول', 'جاکفشی', 'آینه قدی', 'تابلو دیواری', 'فرش دستباف',
        ],
        'tools' => [
            'دریل برقی', 'آچار فرانسه', 'پیچ‌گوشتی', 'چکش', 'اره برقی', 'سنباده‌زن',
            'قفل‌ساز', 'متر', 'تراز', 'گاز انبر', 'انبردست', 'قیچی فلزبر', 'جوشکاری',
            'دستگاه برش', 'جعبه ابزار',
        ],
    ];

    /** Adjectives that can be prefixed to a product name to add variety */
    protected static array $adjectives = [
        'ایرانی', 'وارداتی', 'درجه یک', 'مرغوب', 'اقتصادی', 'حرفه‌ای', 'صنعتی',
        'خانگی', 'سبک', 'سنگین', 'کوچک', 'بزرگ', 'ویژه', 'لوکس', 'استاندارد',
    ];

    // ------------------------------------------------------------------ //
    //  Public API
    // ------------------------------------------------------------------ //

    /**
     * Return a random Persian product name (from any category).
     */
    public function persianProductName(): string
    {
        $categoryKey = static::randomKey(static::$productsByCategory);

        return static::randomElement(static::$productsByCategory[$categoryKey]);
    }

    /**
     * Return a random Persian product name from a given category key.
     * Valid keys: electronics, office, food, clothing, appliances,
     *             building, hygiene, stationery, furniture, tools.
     */
    public function persianProductNameFromCategory(string $category): string
    {
        $products = static::$productsByCategory[$category]
            ?? throw new \InvalidArgumentException("Unknown product category: \"$category\"");

        return static::randomElement($products);
    }

    /**
     * Return a random Persian product name with a random adjective prefix.
     * Example: "حرفه‌ای لپ‌تاپ"
     */
    public function persianProductNameWithAdjective(): string
    {
        $adjective = static::randomElement(static::$adjectives);
        $name      = $this->persianProductName();

        return "$adjective $name";
    }

    /**
     * Return the Persian label for a random category.
     * Example: "الکترونیک"
     */
    public function persianProductCategory(): string
    {
        return static::randomElement(array_values(static::$categories));
    }

    /**
     * Return all available category keys.
     *
     * @return string[]
     */
    public static function productCategoryKeys(): array
    {
        return array_keys(static::$categories);
    }
}

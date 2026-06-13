<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    private const PERSIAN_COMMENTS = [
        'همکاری بسیار خوبی داشتیم و تسویه حساب به‌موقع انجام شد.',
        'مشتری خوش‌حساب و قابل اعتماد است.',
        'سفارش‌ها را همیشه با دقت و وسواس بررسی می‌کند.',
        'پرداخت‌ها با کمی تأخیر انجام می‌شود اما در نهایت تسویه می‌گردد.',
        'از کیفیت محصولات و خدمات ابراز رضایت کرد.',
        'تماس‌ها و پیگیری‌ها به‌خوبی پاسخ داده می‌شود.',
        'درخواست تخفیف برای خریدهای عمده داشت.',
        'سفارش این ماه نسبت به ماه گذشته افزایش داشته است.',
        'لازم است فاکتورهای معوق این مشتری پیگیری شود.',
        'مشتری وفادار با سابقه خرید طولانی و منظم.',
        'هماهنگی برای ارسال بار به انبار مشتری انجام شد.',
        'پیشنهاد عقد قرارداد سالانه به مشتری داده شد.',
        'از پشتیبانی پس از فروش بسیار راضی بود.',
        'مشتری خواستار اصلاح آدرس و اطلاعات تماس شد.',
        'بازخورد مثبتی درباره تحویل سریع سفارش داشت.',
    ];

    public function definition()
    {
        return [
            'user_id' => User::withoutGlobalScopes()->inRandomOrder()->first()->id,
            'content' => $this->faker->randomElement(self::PERSIAN_COMMENTS),
            'rating' => $this->faker->randomFloat(2, 0, 5),
        ];
    }

    public function withCustomer(Customer $customer): static
    {
        return $this->state([
            'customer_id' => $customer->id,
        ]);
    }
}

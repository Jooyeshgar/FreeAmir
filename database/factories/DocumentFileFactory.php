<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFileFactory extends Factory
{
    protected $model = DocumentFile::class;

    private const PERSIAN_FILE_TITLES = [
        'تصویر فاکتور',
        'رسید بانکی',
        'فیش واریزی',
        'قرارداد همکاری',
        'سند هزینه',
        'صورت‌حساب پیوست',
        'مدارک پشتیبان سند',
        'رسید تحویل کالا',
    ];

    public function definition()
    {
        return [
            'user_id' => User::withoutGlobalScopes()->inRandomOrder()->first()->id,
            'title' => $this->faker->randomElement(self::PERSIAN_FILE_TITLES),
            'name' => $this->faker->randomElement(self::PERSIAN_FILE_TITLES).'-'.$this->faker->randomNumber(4),
            'path' => '/document-'.$this->faker->randomDigit.'/file-'.$this->faker->randomNumber,
        ];
    }

    public function withDocument(Document $document): static
    {
        return $this->state([
            'document_id' => $document->id,
        ]);
    }
}

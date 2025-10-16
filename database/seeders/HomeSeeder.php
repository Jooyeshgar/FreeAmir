<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentService;
use Faker\Factory as Faker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class HomeSeeder extends Seeder
{
    protected $faker;

    public function __construct()
    {
        $this->faker = Faker::create('fa_IR');
    }

    public function run(): void
    {

        $banks = [
            ['name' => 'ملی', 'code' => '010001', 'parent_id' => 1, 'type' => 'both', 'company_id' => 1],
            ['name' => 'ملت', 'code' => '010002', 'parent_id' => 1, 'type' => 'both', 'company_id' => 1],
            ['name' => 'شهر', 'code' => '010003', 'parent_id' => 1, 'type' => 'both', 'company_id' => 1],
        ];

        Subject::insert($banks);

        $cashBooks = [
            ['name' => 'صندوق ۱', 'code' => '011001001', 'parent_id' => 14, 'type' => 'both', 'company_id' => 1],
            ['name' => 'صندوق ۲', 'code' => '011001002', 'parent_id' => 14, 'type' => 'both', 'company_id' => 1],
        ];

        Subject::insert($cashBooks);

        for ($i = 0; $i < 150; $i++) {
            $invoice = $this->createInvoice();
            $this->createTransaction($invoice);
        }
    }

    private function createInvoice(): Invoice|Model
    {
        $jalaliYear = 1404;
        $jalaliMonth = rand(1, 12);

        $jalaliDay = rand(1, 28);

        $jalaliDate = $jalaliYear.'/'.
            ($jalaliMonth < 10 ? '0'.$jalaliMonth : $jalaliMonth).'/'.
            ($jalaliDay < 10 ? '0'.$jalaliDay : $jalaliDay);

        $date = jalali_to_gregorian_date($jalaliDate);

        $amount = $this->faker->randomFloat(2, 1000, 10000);
        $user = User::inRandomOrder()->first();

        $customer = Customer::inRandomOrder()->first() ?? Customer::factory()->create();

        $document = DocumentService::createDocument(
            $user,
            [
                'date' => $date,
            ],
            []
        );

        $invoice = Invoice::create([
            'number' => $this->faker->unique()->numerify('#####'),
            'date' => $date,
            'document_id' => $document->id,
            'customer_id' => $customer->id,
            'subtraction' => $this->faker->randomFloat(2, 0, 1000),
            'ship_date' => $this->faker->optional()->date(),
            'ship_via' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'invoice_type' => $this->faker->randomElement(['buy', 'sell']),
            'active' => true,
            'vat' => $this->faker->randomNumber(5),
            'amount' => $amount,
        ]);

        return $invoice;
    }

    private function createTransaction(Invoice $invoice): void
    {
        $description = $this->faker->sentence();

        $invoiceItem = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => $description,
        ]);

        DocumentService::createTransaction(
            $invoice->document,
            [
                'value' => $invoiceItem->amount,
                'subject_id' => Subject::whereIn('parent_id', [1, 14, 23])->inRandomOrder()->first()->id,
                'user_id' => $invoice->creator_id ?? User::inRandomOrder()->first()->id,
                'desc' => $description,
                'created_at' => $invoice->date,
                'updated_at' => $invoice->date,
            ]
        );

        DocumentService::createTransaction(
            $invoice->document,
            [
                'value' => -1 * $invoiceItem->amount,
                'subject_id' => Subject::whereNotIn('parent_id', [1, 14, 23])->inRandomOrder()->first()->id,
                'user_id' => $invoice->creator_id ?? User::inRandomOrder()->first()->id,
                'desc' => $description,
                'created_at' => $invoice->date,
                'updated_at' => $invoice->date,
            ]
        );
    }
}

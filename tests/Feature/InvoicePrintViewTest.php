<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class InvoicePrintViewTest extends TestCase
{
    public function test_it_renders_the_invoices_company_information_instead_of_static_company_details(): void
    {
        $publicDisk = Mockery::mock(FilesystemAdapter::class);
        $publicDisk->shouldReceive('exists')->once()->with('company-logos/current.svg')->andReturnTrue();
        $publicDisk->shouldReceive('get')->once()->with('company-logos/current.svg')->andReturn('<svg></svg>');
        Storage::shouldReceive('disk')->twice()->with('public')->andReturn($publicDisk);

        $company = new Company([
            'name' => 'شرکت نمونه امروز',
            'logo' => 'company-logos/current.svg',
            'address' => 'تهران، خیابان نمونه',
            'economical_code' => '123456789012',
            'national_code' => '12345678901',
            'postal_code' => '1234567890',
            'phone_number' => '09123456789',
        ]);

        $invoice = new Invoice([
            'number' => 12,
            'date' => Carbon::parse('2026-08-19'),
            'invoice_type' => InvoiceType::SELL,
            'amount' => 0,
            'vat' => 0,
            'description' => '',
        ]);
        $invoice->setRelation('company', $company);
        $invoice->setRelation('customer', new Customer(['name' => 'مشتری نمونه']));
        $invoice->setRelation('items', new Collection);

        $html = view('invoices.print', compact('invoice'))->render();

        $this->assertStringContainsString($company->name, $html);
        $this->assertStringContainsString($company->address, $html);
        $this->assertStringContainsString(localizeNumber($company->national_code), $html);
        $this->assertStringContainsString(localizeNumber($company->economical_code), $html);
        $this->assertStringContainsString(localizeNumber($company->postal_code), $html);
        $this->assertStringContainsString(localizeNumber($company->phone_number), $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,'.base64_encode('<svg></svg>'), $html);
        $this->assertStringNotContainsString('شرکت مهندسی جویشگر پردیس ارم', $html);
        $this->assertStringNotContainsString('411337894159', $html);
    }
}

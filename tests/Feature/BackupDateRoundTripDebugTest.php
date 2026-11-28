<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BackupDateRoundTripDebugTest extends TestCase
{
    use RefreshDatabase;

    public static function copyFormats(): array
    {
        return [['serialized_json'], ['direct_copy'], ['plain_local_dates']];
    }

    #[DataProvider('copyFormats')]
    public function test_copy_preserves_business_dates_approval_dates_and_transaction_instants(string $format): void
    {
        config(['app.timezone' => 'Asia/Tehran']);
        $year = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $year->id]);
        $this->actingAs(User::factory()->create(['name' => 'مریم احمدی']));
        $cash = Subject::factory()->create(['parent_id' => null, 'fiscal_year_id' => $year->id]);
        $document = Document::factory()->create(['date' => '2026-11-23', 'approved_at' => '2026-11-24', 'approver_id' => auth()->id()]);
        $row = Transaction::factory()->create(['subject_id' => $cash->id, 'document_id' => $document->id, 'value' => 125.25, 'created_at' => '2026-11-23 00:15:00', 'updated_at' => '2026-11-24 01:20:00']);
        Transaction::factory()->create(['subject_id' => $cash->id, 'document_id' => $document->id, 'value' => -125.25]);
        $data = FiscalYearService::exportData($year->id, ['subjects', 'documents']);
        if ($format === 'serialized_json') {
            $data = json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        } elseif ($format === 'plain_local_dates') {
            $data['documents'][0]['date'] = '2026-11-23';
            $data['documents'][0]['approved_at'] = '2026-11-24';
            $data['transactions'][0]['created_at'] = '2026-11-23 00:15:00';
            $data['transactions'][0]['updated_at'] = '2026-11-24 01:20:00';
        }
        $restored = FiscalYearService::importData($data, ['name' => 'شرکت فرضی بازگردانی تاریخ', 'fiscal_year' => 1405]);
        config(['active-fiscal-year-id' => $restored->id]);
        $actual = Document::where('number', $document->number)->firstOrFail();
        $this->assertSame('2026-11-23', $actual->date->toDateString());
        $this->assertSame('2026-11-24', $actual->approved_at->toDateString());
        $actualRow = $actual->transactions()->where('value', 125.25)->firstOrFail();
        $this->assertSame($row->fresh()->created_at->timestamp, $actualRow->created_at->timestamp);
        $this->assertSame($row->fresh()->updated_at->timestamp, $actualRow->updated_at->timestamp);
        $this->assertSame(0.0, (float) $actual->transactions()->sum('value'));
    }
}

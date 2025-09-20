# راهنمای توسعه امیر

این راهنما بهترین شیوه‌های توسعه در پروژه امیر را توضیح می‌دهد.

## 🎯 اصول کلی توسعه

### 1. اولویت دقت بر سرعت
در سیستم‌های مالی، یک خطای محاسباتی می‌تواند بحران‌آفرین باشد. همیشه:
- موازنه مالی را کنترل کنید
- از Transaction های پایگاه داده استفاده کنید
- اعتبارسنجی کامل انجام دهید

### 2. رعایت اصول SOLID
- **Single Responsibility**: هر کلاس یک مسئولیت
- **Open/Closed**: باز برای توسعه، بسته برای تغییر
- **Liskov Substitution**: امکان جایگزینی زیرکلاس‌ها
- **Interface Segregation**: رابط‌های مختص
- **Dependency Inversion**: وابستگی به انتزاع

### 3. استفاده از معماری Service Layer
منطق پیچیده کسب‌وکار در کلاس‌های Service قرار گیرد، نه Controller.

## 🏭 کار با سرویس‌ها

### DocumentService
سرویس اصلی برای مدیریت اسناد حسابداری:

```php
use App\Services\DocumentService;

// ایجاد سند جدید
$document = DocumentService::createDocument($user, [
    'date' => '2024-01-01',
    'description' => 'شرح سند',
    'number' => 1001
], [
    [
        'subject_id' => $cashAccountId,
        'debit_amount' => 100000,
        'credit_amount' => 0,
        'description' => 'دریافت نقد'
    ],
    [
        'subject_id' => $salesAccountId,
        'debit_amount' => 0,
        'credit_amount' => 100000,
        'description' => 'فروش کالا'
    ]
]);
```

**نکات مهم:**
- همیشه مجموع بدهکار = مجموع بستانکار
- اعتبارسنجی در سطح Service انجام شود
- از DB::transaction استفاده کنید

### FiscalYearService
مدیریت سال‌های مالی و مهاجرت داده:

```php
use App\Services\FiscalYearService;

// صادرات داده‌های سال مالی
$data = FiscalYearService::exportData($fiscalYearId, [
    'subjects',
    'customers', 
    'products'
]);

// وارد کردن به سال جدید
FiscalYearService::importData($newFiscalYearId, $data);
```

## 🎮 کنترلرها (Controllers)

### ساختار استاندارد

```php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Services\DocumentService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct()
    {
        // اختیاری: تنظیمات اولیه
    }
    
    public function index(Request $request)
    {
        // فیلترها و جستجو
        $query = Document::query();
        
        if ($request->has('number')) {
            $query->where('number', $request->number);
        }
        
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        
        $documents = $query->paginate(15);
        
        return view('documents.index', compact('documents'));
    }
    
    public function store(StoreDocumentRequest $request)
    {
        try {
            $document = DocumentService::createDocument(
                auth()->user(),
                $request->validated(),
                $request->transactions
            );
            
            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'سند با موفقیت ایجاد شد');
                
        } catch (DocumentServiceException $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}
```

### اصول کنترلر
- منطق کسب‌وکار در Service باشد
- اعتبارسنجی با Form Request
- پیام‌های کاربرپسند
- مدیریت صحیح خطاها

## 📝 فرم‌های اعتبارسنجی (Form Requests)

### ایجاد Form Request

```bash
php artisan make:request StoreDocumentRequest
```

```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('documents.create');
    }
    
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
            'number' => 'nullable|integer|unique:documents,number',
            'transactions' => 'required|array|min:2',
            'transactions.*.subject_id' => 'required|exists:subjects,id',
            'transactions.*.debit_amount' => 'required|numeric|min:0',
            'transactions.*.credit_amount' => 'required|numeric|min:0',
            'transactions.*.description' => 'nullable|string|max:255'
        ];
    }
    
    public function messages(): array
    {
        return [
            'date.required' => 'تاریخ الزامی است',
            'transactions.min' => 'حداقل دو ردیف تراکنش لازم است',
            'transactions.*.subject_id.exists' => 'سرفصل انتخابی معتبر نیست'
        ];
    }
    
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->hasTransactionBalanceError()) {
                $validator->errors()->add(
                    'transactions', 
                    'مجموع بدهکار و بستانکار برابر نیست'
                );
            }
        });
    }
    
    private function hasTransactionBalanceError(): bool
    {
        $transactions = $this->transactions ?? [];
        $totalDebit = collect($transactions)->sum('debit_amount');
        $totalCredit = collect($transactions)->sum('credit_amount');
        
        return $totalDebit != $totalCredit;
    }
}
```

## 🗃️ مدل‌ها (Models)

### ساختار مدل

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'number',
        'date', 
        'description',
        'fiscal_year_id',
        'company_id',
        'creator_id'
    ];
    
    protected $casts = [
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Relations
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
    
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    
    // Accessors & Mutators
    public function getTotalDebitAttribute(): float
    {
        return $this->transactions->sum('debit_amount');
    }
    
    public function getTotalCreditAttribute(): float
    {
        return $this->transactions->sum('credit_amount');
    }
    
    public function getIsBalancedAttribute(): bool
    {
        return $this->total_debit == $this->total_credit;
    }
    
    // Scopes
    public function scopeForFiscalYear($query, $fiscalYearId)
    {
        return $query->where('fiscal_year_id', $fiscalYearId);
    }
    
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
    
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
```

### اصول مدل
- استفاده از `$fillable` برای Mass Assignment
- تعریف Relations صحیح
- استفاده از Accessors برای محاسبات
- ایجاد Scopes برای کوئری‌های مکرر

## 🎨 ویوها (Views)

### ساختار Blade Template

```php
{{-- resources/views/documents/create.blade.php --}}
@extends('layouts.app')

@section('title', 'ایجاد سند جدید')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>ایجاد سند جدید</h4>
                </div>
                
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    
                    <form method="POST" action="{{ route('documents.store') }}">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date">تاریخ <span class="text-danger">*</span></label>
                                    <input type="date" 
                                           class="form-control @error('date') is-invalid @enderror"
                                           id="date" 
                                           name="date" 
                                           value="{{ old('date', date('Y-m-d')) }}"
                                           required>
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="number">شماره سند</label>
                                    <input type="number" 
                                           class="form-control @error('number') is-invalid @enderror"
                                           id="number" 
                                           name="number" 
                                           value="{{ old('number', $nextNumber) }}">
                                    @error('number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        {{-- بخش تراکنش‌ها --}}
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5>ردیف‌های سند</h5>
                            </div>
                            <div class="card-body">
                                <div id="transactions-container">
                                    {{-- ردیف‌های تراکنش به صورت داینامیک اضافه می‌شوند --}}
                                </div>
                                
                                <button type="button" class="btn btn-secondary" id="add-transaction">
                                    افزودن ردیف
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group mt-3">
                            <button type="submit" class="btn btn-primary">ذخیره سند</button>
                            <a href="{{ route('documents.index') }}" class="btn btn-secondary">انصراف</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // منطق JavaScript برای مدیریت ردیف‌های تراکنش
    let transactionIndex = 0;
    
    function addTransactionRow() {
        // کد ایجاد ردیف جدید
    }
    
    function calculateBalance() {
        // محاسبه موازنه
    }
    
    document.getElementById('add-transaction').addEventListener('click', addTransactionRow);
});
</script>
@endpush
```

### اصول View
- استفاده از Layout های مشترک
- نمایش خطاها با Bootstrap
- JavaScript در `@push('scripts')`
- RTL Support برای فارسی

## 🧪 تست‌نویسی

### تست‌های Feature

```php
<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Document;
use App\Models\Subject;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_user_can_create_balanced_document()
    {
        // Arrange
        $user = User::factory()->create();
        $cashAccount = Subject::factory()->create(['type' => 'asset']);
        $salesAccount = Subject::factory()->create(['type' => 'income']);
        
        // Act
        $this->actingAs($user);
        
        $response = $this->post('/documents', [
            'date' => '2024-01-01',
            'description' => 'Test Document',
            'transactions' => [
                [
                    'subject_id' => $cashAccount->id,
                    'debit_amount' => 100000,
                    'credit_amount' => 0,
                    'description' => 'Cash received'
                ],
                [
                    'subject_id' => $salesAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 100000,
                    'description' => 'Sales income'
                ]
            ]
        ]);
        
        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('documents', [
            'description' => 'Test Document'
        ]);
        
        $document = Document::where('description', 'Test Document')->first();
        $this->assertTrue($document->is_balanced);
    }
    
    public function test_cannot_create_unbalanced_document()
    {
        // Arrange
        $user = User::factory()->create();
        $cashAccount = Subject::factory()->create();
        $salesAccount = Subject::factory()->create();
        
        // Act
        $this->actingAs($user);
        
        $response = $this->post('/documents', [
            'date' => '2024-01-01',
            'transactions' => [
                [
                    'subject_id' => $cashAccount->id,
                    'debit_amount' => 100000,
                    'credit_amount' => 0
                ],
                [
                    'subject_id' => $salesAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 50000  // عدم موازنه
                ]
            ]
        ]);
        
        // Assert
        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('documents', [
            'date' => '2024-01-01'
        ]);
    }
}
```

### تست‌های Unit

```php
<?php
namespace Tests\Unit;

use App\Services\DocumentService;
use App\Exceptions\DocumentServiceException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_validates_document_balance()
    {
        // Arrange
        $user = User::factory()->create();
        $documentData = ['date' => '2024-01-01'];
        $unbalancedTransactions = [
            ['debit_amount' => 100, 'credit_amount' => 0],
            ['debit_amount' => 0, 'credit_amount' => 50]
        ];
        
        // Act & Assert
        $this->expectException(DocumentServiceException::class);
        DocumentService::createDocument($user, $documentData, $unbalancedTransactions);
    }
}
```

## 📊 کار با داده‌ها

### Eager Loading
```php
// Bad: N+1 Problem
foreach ($documents as $document) {
    echo $document->creator->name;  // هر بار کوئری جدید
}

// Good: Eager Loading
$documents = Document::with('creator', 'transactions.subject')->get();
foreach ($documents as $document) {
    echo $document->creator->name;  // بدون کوئری اضافی
}
```

### استفاده از Scopes
```php
// استفاده در Controller
$documents = Document::forFiscalYear($fiscalYearId)
                    ->forCompany($companyId)
                    ->inDateRange($startDate, $endDate)
                    ->with('creator')
                    ->paginate(15);
```

### محاسبات مالی دقیق
```php
// Bad: استفاده از float
$total = $amount1 + $amount2;

// Good: استفاده از bcmath
$total = bcadd($amount1, $amount2, 2);

// Better: استفاده از کلاس Money (اختیاری)
use Money\Money;
$amount = new Money(10000, new Currency('IRR'));
```

## 🔧 بهینه‌سازی عملکرد

### Caching
```php
// Cache نتایج محاسبات سنگین
$balance = Cache::remember("account_balance_{$accountId}", 3600, function() use ($accountId) {
    return Transaction::where('subject_id', $accountId)
        ->sum(DB::raw('debit_amount - credit_amount'));
});
```

### استفاده از Queue برای عملیات سنگین
```php
// برای گزارش‌های بزرگ
dispatch(new GenerateReportJob($reportParams));
```

### Database Indexes
```php
// در مایگریشن
Schema::table('transactions', function (Blueprint $table) {
    $table->index(['subject_id', 'date']);
    $table->index(['document_id']);
});
```

## 🛡️ امنیت

### اعتبارسنجی ورودی
```php
// همیشه ورودی‌ها را اعتبارسنجی کنید
$validated = $request->validate([
    'amount' => 'required|numeric|min:0|max:999999999'
]);
```

### محافظت از CSRF
```php
// در فرم‌ها
@csrf

// در AJAX
headers: {
    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
}
```

### کنترل دسترسی
```php
// در Controller
$this->authorize('update', $document);

// در Blade
@can('update', $document)
    <button>ویرایش</button>
@endcan
```

## 📝 مستندسازی کد

### DocBlocks
```php
/**
 * ایجاد سند حسابداری جدید با کنترل موازنه
 *
 * @param User $user کاربر ایجادکننده سند
 * @param array $data اطلاعات سند (تاریخ، شماره، شرح)
 * @param array $transactions آرایه تراکنش‌ها
 * @return Document سند ایجاد شده
 * @throws DocumentServiceException در صورت عدم موازنه یا خطای اعتبارسنجی
 */
public static function createDocument(User $user, array $data, array $transactions): Document
{
    // ...
}
```

### کامنت‌های فارسی
```php
// محاسبه مجموع بدهکار و بستانکار برای کنترل موازنه
$totalDebit = collect($transactions)->sum('debit_amount');
$totalCredit = collect($transactions)->sum('credit_amount');

// بررسی موازنه سند
if ($totalDebit !== $totalCredit) {
    throw new DocumentServiceException('سند متوازن نیست');
}
```

---

**نکته نهایی**: همیشه از این اصول پیروی کنید و کدهای خود را با کدهای موجود در پروژه هماهنگ نگه دارید.

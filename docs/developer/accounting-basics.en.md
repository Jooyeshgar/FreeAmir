# Accounting Basics for FreeAmir Developers

This guide is for developers who want to contribute to **FreeAmir** and need to understand its accounting concepts and code structure.

## Why should developers understand accounting?

A lack of accounting knowledge can lead to:

- Business-logic bugs
- Incorrect database designs
- Failure to keep financial postings balanced
- Incorrect reports
- Violations of accounting rules

**Important:** In financial systems, a small programming error can leave accounts out of balance and cause serious financial problems.

## Accounting basics

### What are debit and credit?

- **Debit:** transactions with a negative value; increases an asset or expense, or decreases a liability.
- **Credit:** transactions with a positive value; decreases an asset or expense, or increases a liability or revenue.

In FreeAmir, transactions are stored in a `value` field:

- Negative value = debit
- Positive value = credit

```php
// Example: buying goods for 100,000 tomans in cash
// Transaction 1: increase inventory (debit)
$transaction1 = Transaction::create([
    'subject_id' => $inventorySubject->id,
    'value' => -100000,  // negative = debit
    'desc' => 'Purchase of goods'
]);

// Transaction 2: decrease cash (credit)
$transaction2 = Transaction::create([
    'subject_id' => $cashSubject->id,
    'value' => 100000,  // positive = credit
    'desc' => 'Cash payment'
]);
```

### The golden rule: balance

**In every document, total debits must equal total credits.**
That means the sum of all transactions must be zero.

**Important:** Documents are not always balanced while they are being entered. For convenience, FreeAmir allows unbalanced documents to be saved, but:

1. **Manual documents:** may be saved unbalanced.
2. **Automatic documents:** must always be balanced (invoices, checks, and others).
3. **Final approval:** only balanced documents can receive final approval.

```php
$totalValue = $document->transactions->sum('value');
if ($totalValue != 0) {
    throw ValidationException::withMessages([
        'transactions' => ['The document is not balanced'],
    ]);
}
```

## Accounting subject hierarchy

### Main subjects

```text
010 - Banks
011 - Cash holdings
├── 011001 - Cash desk
└── 011002 - Petty cash

012 - Debtors/Creditors
└── 012001 - Miscellaneous persons

040 - Expenses
├── 040001 - Employee salaries
├── 040002 - Water
├── 040003 - Electricity

041 - Cost of goods sold
└── 041001 - Cost of goods sold

050 - Revenue
└── 050001 - Miscellaneous revenue

060 - Sales
└── 060001 - Sales
```

### Subject coding

**Important:** In FreeAmir, subject codes are built in groups of three digits:

- General subject: `010`
- Subsidiary subject: `011001`
- Detailed subject: `011001001`
- Next detailed level: `011001001001`
- And so on, with no fixed depth limit

```php
// Example: coding structure in the subjects table
'code'      => '011001',            // Cash desk subject code
'name'      => 'Cash desk',         // Subject name
'parent_id' => 3,                   // Parent ID (cash holdings)
'type'      => 'both'               // Account type
```

### Relationship between groups and subjects

```php
$customerGroupConfig = Config::where('key', 'customer_default_subject')
                            ->where('fiscal_year_id', getActiveFiscalYear())
                            ->value('value');

// Which subjects are linked to product groups?
$productInventoryConfig = Config::where('key', 'product_inventory_subject')
                               ->where('fiscal_year_id', getActiveFiscalYear())
                               ->value('value');
```

### Use the service to manage codes

**Important warning:** Always use `generateCode()` on the `Subject` model to generate subject codes:

```php
$subject = new Subject();
$subject->name = 'Main branch cash desk';
$subject->parent_id = $parentSubject->id;
$subject->type = 'both';
// The code is generated automatically
$subject->save();

// Or, to set a specific code:
$subject->code = $subject->generateCode(15); // Code 015
```

### Displaying codes

Use the formatting methods on the model to display codes:

```php
echo $subject->formattedCode();  // "011/001"

// Display the code with the name
echo $subject->formattedName(); // "011/001 Cash desk"
```

## Customer and product groups and their subjects

### Grouping concept

In FreeAmir, each customer or product group is linked to a subsidiary subject. This relationship is defined through configuration settings.

### Customers

```php
$customerGroup = CustomerGroup::create([  // Each customer group is linked to a subject
    'name' => 'General customers',
    'subject_id' => 58  // Miscellaneous persons subject (012001)
]);

// A detailed subject is created when a customer is added
$customer = Customer::create([
    'name' => 'Mr. Mohammadi',
    'group_id' => $customerGroup->id
]);

// New subject: Miscellaneous persons/Mohammadi, with a code such as 012001001
```

### Goods and services

```php
$productGroup = ProductGroup::create([// Each product group is also linked to several subjects
    'name' => 'Staple goods',
    'inventory_subject_id' => 70,             // Inventory account (015002)
    'income_subject_id' => 20,                // Sales revenue account (060001)
    'expense_subject_id' => 89                // Cost of goods sold account (041001)
]);

// Detailed subjects are created when a product is added
$product = Product::create([
    'name' => 'Tarom rice',
    'group_id' => $productGroup->id
]);
```

### Unlimited detailed subject levels

FreeAmir supports detailed subjects at unlimited levels:

```php
// Level 1: 010
// Level 2: 011001
// Level 3: 011001001
// Level 4: 011001001001
// Level 5: 011001001001001
// And so on without limit
```

## Documents and transactions: document and transaction structure

### Overall structure

```php
class Document extends Model {
    protected $fillable = [
        'number',       // Document number (unique within a fiscal year)
        'date',         // Document date
        'title',        // General document description
        'approved_at',  // Document approval date
        'creator_id',   // ID of the user who created it
        'approver_id',  // ID of the user who approved it
        'fiscal_year_id',   // Fiscal year ID
    ];

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'date',
    ];

    public function transactions() {
        return $this->hasMany(Transaction::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function approver() {
        return $this->belongsTo(User::class, 'approver_id');
    }
}

class Transaction extends Model {
    protected $fillable = [
        'subject_id',   // Subject ID
        'document_id',  // Document ID
        'user_id',      // User ID
        'desc',         // Transaction description
        'value',        // Amount (negative = debit, positive = credit)
    ];

    // Automatically calculate debit and credit values
    public function getDebitAttribute() {
        return $this->value < 0 ? formatNumber(-1 * $this->value) : '';
    }

    public function getCreditAttribute() {
        return $this->value > 0 ? formatNumber($this->value) : '';
    }
}
```

### Example document in code

```php
// Example: recording a cash sale of 500,000 tomans
$documentData = [
    'date' => '2024-01-01',
    'title' => 'Sale of goods to a customer'
];

// Form input (the data received by DocumentController::store)
$requestTransactions = [
    [
        'subject_id' => $cashSubject->id,
        'debit' => 500000,
        'credit' => 0,
        'desc' => 'Cash received',
    ],
    [
        'subject_id' => $salesSubject->id,
        'debit' => 0,
        'credit' => 500000,
        'desc' => 'Revenue from sale',
    ],
];

$transactionsData = [];
foreach ($requestTransactions as $transactionData) {
    $transactionsData[] = [
        'subject_id' => $transactionData['subject_id'],
        'value' => $transactionData['credit'] - $transactionData['debit'], // DocumentController::store logic
        'desc' => $transactionData['desc'],
    ];
}

// Result: negative value = debit; positive value = credit
// [
//     ['subject_id' => $cashSubject->id, 'value' => -500000, 'desc' => 'Cash received'],   // debit
//     ['subject_id' => $salesSubject->id, 'value' => 500000, 'desc' => 'Revenue from sale'], // credit
// ]

$document = DocumentService::createDocument(
    auth()->user(),
    $documentData,
    $transactionsData
);
```

## Essential FreeAmir services

### DocumentService

**Important:** Always use this service when working with documents:

```php
use App\Services\DocumentService;

// Create a new document
$document = DocumentService::createDocument($user, $documentData, $transactionsData);

// Update a document
$document = DocumentService::updateDocument($document, $newData);

// Approve a document (checks that it is balanced)
DocumentService::approveDocument($document);

// Create a transaction
$transaction = DocumentService::createTransaction($document, $transactionData);

// Update a document's transactions
DocumentService::updateDocumentTransactions($documentId, $transactionsData);

// Delete a document (including its transactions)
DocumentService::deleteDocument($documentId);
```

### SubjectCreatorService

Use this service to create subjects and generate their accounting codes:

```php
use App\Models\Subject;
use App\Services\SubjectCreatorService;

$subjectService = new SubjectCreatorService();

// First, find the parent subject (for example, the main cash desk)
$parentSubject = Subject::where('code', '011001')->firstOrFail();

$subject = $subjectService->createSubject([
    'name' => 'New cash desk',
    'parent_id' => $parentSubject->id,
    'type' => 'debtor',   // Optional; the default is 'both'
]);
```

### FiscalYearService

Use this service to manage fiscal years and migrate data:

```php
use App\Services\FiscalYearService;

// Export fiscal-year data
$data = FiscalYearService::exportData($fiscalYearId, [
    'subjects',
    'customers',
    'products',
    'documents'
]);

// Import into a new fiscal year
$newFiscalYear = FiscalYearService::importData($data, $newFiscalYearData);

// Get the sections available for export
$availableSections = FiscalYearService::getAvailableSections();
```

## Fiscal years and multiple companies

A `Company` stores business identity and can have multiple `FiscalYear` records. The `FiscalYear` model stores the year and closing status. Documents, invoices, and related records point to the relevant fiscal year through `fiscal_year_id`.

```php
$company = Company::with('fiscalYears')->findOrFail($companyId);
$fiscalYear = $company->fiscalYears()->where('year', 1405)->firstOrFail();

// Access is assigned separately for each fiscal year.
$user->fiscalYears()->syncWithoutDetaching([$fiscalYear->id]);
```

### Data isolation with FiscalYearScope

Models that are scoped to a fiscal year use `FiscalYearScope`:

```php
protected static function booted()
{
    static::addGlobalScope(new FiscalYearScope);
}

// For exceptional work without the scope
$allData = SomeModel::withoutGlobalScope(FiscalYearScope::class)->get();
```

### Fiscal-year migration and copying

For more information, see [Fiscal-Year Export and Import](FiscalYearExportImport.en.md).

```php
php artisan fiscal-year:export 1 --sections=subjects,customers
php artisan fiscal-year:import exported_data.json 1404 --name="Example Company"
```

After importing through the CLI command, assign users access to the new fiscal year in user management.

### Company settings

Settings in the `configs` table are stored separately for each company:

## Financial reports

### Journal

**Definition:** A list of all documents and transactions in the order they were recorded.

**Characteristics:**

- Displays **all transactions**, without exception
- Sorted by **date** and **document number**
- Includes all subject levels (general, subsidiary, and detailed)
- Each row represents one transaction
- Displays the debit and credit for each transaction

**Uses:**

- Reviewing the timing of financial operations
- Tracing specific transactions
- Checking document sequence

**Important points:**

- Useful for manually reviewing accounts
- Can be filtered by date, subject, or document type
- In FreeAmir, it includes both temporary and permanent documents

### General ledger

**Definition:** Transactions collected and summarized by accounting subject.

**Difference from the journal:**

- **Journal:** all transactions ordered by date
- **General ledger:** transactions grouped by subject

**Ledger types in FreeAmir:**

#### 1. General ledger (general subjects)

- Displays only **general subjects** (such as assets and liabilities)
- Suitable for reviewing the overall financial position

#### 2. Subsidiary ledger (subsidiary subjects)

- Displays **second-level subjects** (such as cash and bank, and creditors)
- Provides more detail than the general ledger

#### 3. Detailed ledger (detailed subjects)

- Displays **one selected subject** and all its transactions
- In FreeAmir, general, subsidiary, and detailed ledgers appear together in one integrated form

**Example:**

```text
Selected subject: "Cash desk" (011/001)
Result: all transactions related to this specific cash desk
```

**Important points:**

- Used to calculate the balance of each account
- Can show the cumulative balance at any date
- In FreeAmir, selecting a parent subject also shows transactions for its child subjects

### Balance sheet

**Definition:** A report of the financial position on a specific date (a snapshot of assets, liabilities, and equity).

**Traditional structure:**

```text
Assets = Liabilities + Equity
```

**Contents:**

- **Current assets:** cash, receivables, inventory
- **Fixed assets:** buildings, machinery, equipment
- **Current liabilities:** payables, taxes payable
- **Long-term liabilities:** bank loans
- **Equity:** initial capital, retained earnings

**Important points:**

- It must always balance (assets = liabilities + equity)
- Includes only **permanent accounts** (assets, liabilities, and equity)
- **Temporary accounts** (revenue and expenses) are not shown on the balance sheet
- In FreeAmir, the current year's profit or loss is automatically included in equity

**Difference from other reports:**

- Journal: all transactions
- General ledger: transactions grouped by subject
- Balance sheet: account balances on a specific date

### Income statement

**Definition:** A report of financial performance over a **period of time** (not on a specific date).

**Contents:**

- **Revenue:** sales and non-operating revenue
- **Expenses:** cost of goods sold, operating expenses, and financial expenses
- **Net profit/loss:** revenue minus expenses

**Traditional structure:**

```text
Sales revenue
- Cost of goods sold
= Gross profit

- Operating expenses
= Operating profit

+ Non-operating revenue
- Non-operating expenses
= Net profit
```

**Key differences:**

| Feature | Balance sheet | Income statement |
|--------|---------------|------------------|
| Time | A specific date | A period of time |
| Accounts | Permanent (assets, liabilities, equity) | Temporary (revenue, expenses) |
| Purpose | Financial position | Financial performance |
| Reset | Carries forward annually | Resets annually |

**Important points in FreeAmir:**

- Revenue and expense accounts are transferred to the "retained earnings" account at year-end
- Reports can be produced for different periods (monthly, quarterly, or annually)
- A loss is reflected as a negative amount in equity

**How the reports relate:**

1. **Journal** → raw data for all transactions
2. **General ledger** → transactions aggregated by subject
3. **Balance sheet** → permanent-account balances on a specific date
4. **Income statement** → performance of temporary accounts over a period

**Important:** All these reports in FreeAmir are generated from one integrated database and must reconcile with one another.

## Settings and configuration

### The design philosophy behind Config in FreeAmir

To avoid hard-coding relationships between different parts of the system, all connections and settings are stored in the `configs` table. This lets users customize settings without changing the code.

### ConfigLoader middleware

`ConfigLoader` reads all settings from the database and loads them into Laravel's `config()` so they are available throughout the application.

**Features:**

- Automatically reads all configs from the database
- Loads them into `config('amir.key')` for easy access
- Runs on every HTTP request

For more information, see `ConfigController`.

### Accessing settings

**In Laravel code:**

```php
$cashSubjectId = config('amir.cash_book');

// With a default value
$bankSubjectId = config('amir.bank', null);
```

**For company-specific settings:**

```php
$config = Config::where('fiscal_year_id', getActiveFiscalYear())
               ->where('key', 'cash_book')
               ->value('value');
```

## Security and access control

### Roles in a financial system

FreeAmir uses [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6/introduction) to manage access:

```php
'accounting.documents.create'
'accounting.documents.edit'
'accounting.documents.delete'
'accounting.reports.view'
'accounting.settings.manage'
```

## Important practices for developers

### 1. Always check balance (for automatic documents only)

```php
$totalValue = collect($transactions)->sum('value'); // For automatic documents (invoices, checks, etc.)
if ($totalValue != 0) {
    throw new ValidationException('An automatic document must be balanced');
}

// Balance is not required for manual documents
```

### 2. Use database transactions

```php
DB::transaction(function() {
    // Financial operations
    $document = DocumentService::createDocument($user, $data, $transactions);
});
```

### 3. Always use the provided services

```php
$document = Document::create($data); // Wrong ❌

// Correct ✅
$document = DocumentService::createDocument($user, $data, $transactions);
```

### 5. Use subject codes correctly

```php
$subject->code = $subject->generateCode();

// To display the formatted code
echo $subject->formattedCode();  // "001/002/003"
```

### 7. Always consider the fiscal-year scope

```php
$subjects = Subject::all(); // Correct ✅ - uses the scope automatically

// To query without the scope
$allSubjects = Subject::withoutGlobalScope(FiscalYearScope::class)->get();
```

---

**Final note:** In financial systems, accuracy and adherence to accounting standards are more important than speed. Always test your code several times and use the services provided in FreeAmir.

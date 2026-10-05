<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Company extends Model
{
    use HasFactory;

    public const IDENTITY_FIELDS = [
        'name', 'logo', 'address', 'economical_code', 'national_code',
        'postal_code', 'phone_number', 'currency', 'certificate_path',
        'private_key_path', 'moadian_username', 'tax_id',
    ];

    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Company $company): void {
            if ($company->exists && ! $company->isDirty('name') && ! $company->isDirty('fiscal_year')) {
                return;
            }

            $currentIdentityId = $company->exists ? $company->fiscalYear?->company_identity_id : null;
            $sameName = CompanyIdentity::where('name', $company->name)->get()
                ->first(fn (CompanyIdentity $identity) => strcmp($identity->name, $company->name) === 0);
            if ($company->exists && $sameName && $sameName->id !== $currentIdentityId) {
                throw ValidationException::withMessages([
                    'name' => [__('A company with this name already exists.')],
                ]);
            }

            if ($sameName && FiscalYear::where('company_identity_id', $sameName->id)
                ->where('year', $company->fiscal_year)
                ->when($company->exists, fn ($query) => $query->where('legacy_company_id', '!=', $company->id))
                ->exists()) {
                throw ValidationException::withMessages([
                    'fiscal_year' => [__('This fiscal year already exists for the company.')],
                ]);
            }
        });

        static::created(function (Company $company): void {
            $identityId = CompanyIdentity::where('name', $company->name)->get()
                ->first(fn (CompanyIdentity $identity) => strcmp($identity->name, $company->name) === 0)?->id;
            if (! $identityId) {
                $identityId = CompanyIdentity::create($company->only(self::IDENTITY_FIELDS))->id;
            }

            FiscalYear::create([
                'company_identity_id' => $identityId,
                'legacy_company_id' => $company->id,
                'year' => $company->fiscal_year,
                'closed_at' => $company->closed_at,
                'closed_by' => $company->closed_by,
                'pl_document_id' => $company->pl_document_id,
                'closing_document_id' => $company->closing_document_id,
                'closing_recalculation_step' => $company->closing_recalculation_step,
            ]);

            $identity = CompanyIdentity::findOrFail($identityId);
            $sharedDetails = $identity->only(self::IDENTITY_FIELDS);
            if ($sharedDetails['currency'] === null) {
                unset($sharedDetails['currency']);
            }
            DB::table('companies')->where('id', $company->id)->update($sharedDetails);
            $company->setRawAttributes(array_merge($company->getAttributes(), $sharedDetails), true);
        });

        static::updated(function (Company $company): void {
            $company->fiscalYear()->update([
                'year' => $company->fiscal_year,
                'closed_at' => $company->closed_at,
                'closed_by' => $company->closed_by,
                'pl_document_id' => $company->pl_document_id,
                'closing_document_id' => $company->closing_document_id,
                'closing_recalculation_step' => $company->closing_recalculation_step,
            ]);

            $sharedChanges = array_intersect_key($company->getChanges(), array_flip(self::IDENTITY_FIELDS));
            if ($sharedChanges === []) {
                return;
            }

            $identity = $company->fiscalYear?->companyIdentity;
            if (! $identity) {
                return;
            }

            $identity->update($sharedChanges);
            DB::table('companies')->whereIn('id', $identity->fiscalYears()->select('legacy_company_id'))->update($sharedChanges);
        });

        static::deleting(function (Company $company): void {
            $company->fiscalYear()->delete();
        });
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->using(CompanyUserPivot::class);
    }

    public function fiscalYear(): HasOne
    {
        return $this->hasOne(FiscalYear::class, 'legacy_company_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Decrypted Moadian SSL certificate contents, or null if not set.
     */
    public function decryptedCertificate(): ?string
    {
        return $this->readKeyFile($this->certificate_path);
    }

    /**
     * Decrypted Moadian private key contents, or null if not set.
     */
    public function decryptedPrivateKey(): ?string
    {
        return $this->readKeyFile($this->private_key_path);
    }

    private function readKeyFile(?string $path): ?string
    {
        if (! $path || ! Storage::exists($path)) {
            return null;
        }

        $raw = Storage::get($path);

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException $e) {
            return $raw;
        }
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * The Income Summary / P&L document created in Step 1 (closing temporary accounts).
     */
    public function plDocument()
    {
        return $this->belongsTo(Document::class, 'pl_document_id');
    }

    /**
     * The closing document created in Step 3 (closing permanent accounts).
     */
    public function closingDocument()
    {
        return $this->belongsTo(Document::class, 'closing_document_id');
    }
}

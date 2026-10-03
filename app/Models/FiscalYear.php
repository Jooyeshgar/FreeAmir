<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class FiscalYear extends Model
{
    use HasFactory;

    private const SHARED_ATTRIBUTES = [
        'name', 'logo', 'address', 'economical_code', 'national_code',
        'postal_code', 'phone_number', 'currency', 'certificate_path',
        'private_key_path', 'moadian_username', 'tax_id',
    ];

    public $timestamps = false;

    protected $table = 'fiscal_years';

    protected $guarded = [];

    public static function booted(): void
    {
        static::creating(function (FiscalYear $fiscalYear) {
            if (! $fiscalYear->company_id) {
                $attributes = $fiscalYear->only(self::SHARED_ATTRIBUTES);
                $attributes['currency'] ??= 'Rial';
                $company = Company::create($attributes);
                $fiscalYear->company_id = $company->id;
            }

            foreach (self::SHARED_ATTRIBUTES as $field) {
                unset($fiscalYear->attributes[$field]);
            }
        });

        static::saving(function (FiscalYear $fiscalYear) {
            if (! $fiscalYear->exists) {
                return;
            }

            $shared = array_intersect_key($fiscalYear->getDirty(), array_flip(self::SHARED_ATTRIBUTES));

            if ($shared) {
                $fiscalYear->company->update($shared);

                foreach (array_keys($shared) as $field) {
                    unset($fiscalYear->attributes[$field]);
                }
            }
        });

        static::deleted(function (FiscalYear $fiscalYear) {
            $business = $fiscalYear->company;

            if ($business && ! $business->fiscalYears()->exists()) {
                $business->delete();
            }
        });
    }

    public function getAttribute($key)
    {
        if ($this->exists && in_array($key, self::SHARED_ATTRIBUTES, true)) {
            return $this->company?->getAttribute($key);
        }

        return parent::getAttribute($key);
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'fiscal_year_user', 'fiscal_year_id', 'user_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'company_id');
    }

    /**
     * Return the inclusive Gregorian boundaries of this Jalali fiscal year.
     */
    public function fiscalYearRange(): array
    {
        $year = (int) $this->fiscal_year;

        $start = Carbon::parse(jalali_to_gregorian($year, 1, 1, '/'))->startOfDay();
        $end = Carbon::parse(jalali_to_gregorian($year + 1, 1, 1, '/'))->subDay()->endOfDay();

        return [$start, $end];
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

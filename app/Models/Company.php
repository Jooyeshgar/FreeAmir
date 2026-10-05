<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Return the inclusive Gregorian boundaries of this Jalali fiscal year.
     */
    public function fiscalYearRange(?FiscalYear $fiscalYear = null): array
    {
        $fiscalYear ??= $this->fiscalYears()->findOrFail(getActiveFiscalYear());
        $year = (int) $fiscalYear->year;

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
}

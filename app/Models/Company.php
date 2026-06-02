<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

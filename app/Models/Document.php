<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'date',
    ];

    protected $fillable = [
        'number',
        'date',
        'title',
        'permanent',
        'creator_id',
        'company_id',
        'documentable_id',
        'documentable_type',
        'approved_at',
        'approver_id',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function getFormattedDateAttribute()
    {
        return formatDate($this->date ?? now());
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'document_id');
    }

    public function documentable()
    {
        return $this->morphTo();
    }

    public function documentFiles()
    {
        return $this->hasMany(DocumentFile::class);
    }
}

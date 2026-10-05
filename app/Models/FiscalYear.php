<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FiscalYear extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function plDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'pl_document_id');
    }

    public function closingDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'closing_document_id');
    }
}

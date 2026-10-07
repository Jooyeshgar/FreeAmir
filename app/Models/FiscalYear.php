<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Return the inclusive Gregorian boundaries of this Jalali fiscal year.
     */
    public function range(): array
    {
        $year = (int) $this->year;

        $start = Carbon::parse(jalali_to_gregorian($year, 1, 1, '/'))->startOfDay();
        $end = Carbon::parse(jalali_to_gregorian($year + 1, 1, 1, '/'))->subDay()->endOfDay();

        return [$start, $end];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
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

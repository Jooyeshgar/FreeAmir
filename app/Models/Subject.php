<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

class Subject extends Model
{
    use HasFactory, NodeTrait;

    protected $fillable = [
        'code',
        'name',
        'parent_id',
        'company_id',
        'type',
    ];

    protected $attributes = [
        'parent_id' => 0,
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope());
    }

    public function subjectable()
    {
        return $this->morphTo();
    }

    public function formattedCode()
    {
        return formatCode($this->code);
    }

    public function formattedName()
    {
        return formatCode($this->code) . ' ' . $this->name;
    }
}

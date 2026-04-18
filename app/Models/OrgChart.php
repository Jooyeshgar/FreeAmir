<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class OrgChart extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'title',
        'parent_id',
        'description',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgChart::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrgChart::class, 'parent_id');
    }

    public static function getTree(): Collection
    {
        $allNodes = self::all();

        $grouped = $allNodes->groupBy('parent_id');

        foreach ($allNodes as $node) {
            $children = $grouped->get($node->id, collect());

            $node->setRelation('children', $children);
        }

        return $allNodes->whereNull('parent_id');
    }
}

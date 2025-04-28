<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

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

        static::creating(function ($subject) {
            $subject->company_id ??= session('active-company-id');
            $subject->code ??= $subject->generateCode();
        });

        static::deleting(function ($subject) {
            if ($subject->subjectable()->exists()) {
                throw new \Exception(__('Cannot delete subject with relationships'));
            }

            if ($subject->children()->exists()) {
                throw new \Exception(__('Cannot delete subject with children'));
            }

            if ($subject->transactions()->exists()) {
                throw new \Exception(__('Cannot delete subject with transactions'));
            }
        });
    }

    public function subSubjects()
    {
        return $this->hasMany(Subject::class, 'parent_id');
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

    public function ledger()
    {
        return substr($this->code, 0, 3);
    }

    public function parent()
    {
        return $this->belongsTo(Subject::class, 'parent_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function getRoot()
    {
        return $this->hasParent() ? $this->parent->getRoot() : $this;
    }

    public function generateCode($code = null)
    {
        if ($code) {
            return str_pad($code, strlen($code), '0', STR_PAD_LEFT);
        }

        if ($this->hasParent()) {
            $parentSubject = $this->parent;
            if ($parentSubject->hasChildren()) {
                $lastChildCode = $parentSubject->children()->orderBy('code', 'desc')->first()->code;
                return str_pad((int) $lastChildCode + 1, strlen($lastChildCode), '0', STR_PAD_LEFT);
            } else {
                $firstChildBase = $parentSubject->code . '000';
                return str_pad((int) $firstChildBase + 1, strlen($firstChildBase), '0', STR_PAD_LEFT);
            }
        } else {
            $lastRootSubject = Subject::whereNull('parent_id')->orderBy('code', 'desc')->first();
            $baseCode = $lastRootSubject->code + 1 ?? '000';
            return str_pad((int) $baseCode + 1, strlen($baseCode), '0', STR_PAD_LEFT);
        }
    }

    /**
     * Get the children for the subject.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(Subject::class, 'parent_id');
    }

    public function hasChildren()
    {
        return $this->children()->exists();
    }

    public function hasParent(): bool
    {
        return !is_null($this->parent_id);
    }

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function getAllDescendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }

    /**
     * Scope a query to only include root subjects.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereIsRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}

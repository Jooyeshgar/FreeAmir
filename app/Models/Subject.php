<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use App\Models\Traits\QueryHelper;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory, QueryHelper;

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
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function ($subject) {
            $subject->company_id ??= session('active-company-id');
        });

        static::deleting(function ($subject) {
            if (! is_null($subject->subjectable_type) && ! is_null($subject->subjectable_id) && $subject->subjectable()->exists()) {
                throw new Exception(__('Cannot delete subject with relationships'));
            }

            if ($subject->children()->exists()) {
                throw new Exception(__('Cannot delete subject with children'));
            }

            if ($subject->transactions()->exists()) {
                throw new Exception(__('Cannot delete subject with transactions'));
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
        return formatCode($this->code).' '.$this->name;
    }

    public function fullname()
    {
        return (! is_null($this->parent) ? $this->parent->fullname().' / ' : '').$this->name;
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

    /**
     * Generates a hierarchical code for the subject based on parent-child relationships.
     *
     * Format: Parent code + Child sequence (e.g., 001001, 001002, 002001, 002001001)
     * - Root level subjects have 3-digit codes (001, 002, etc.)
     * - Each child level adds 3 digits to the parent code
     * - Maximum children per parent is 999
     *
     * @param  int|null  $code  Optional specific code number to use (without padding)
     * @return string The generated code for the subject
     *
     * @throws \Exception When trying to exceed the maximum of 999 children
     */
    public function generateCode($code = null)
    {
        if ($this->hasParent()) {
            $parentSubject = $this->parent;
            $parentCode = $parentSubject->code;

            if ($code !== null) {
                if ($code > 999) {
                    throw new Exception('Child code cannot exceed 999');
                }

                return $parentCode.str_pad($code, 3, '0', STR_PAD_LEFT);
            }

            if ($parentSubject->hasChildren()) {
                $lastChild = $parentSubject->children()->orderBy('code', 'desc')->first();
                $lastChildCode = $lastChild->code;

                $childPart = substr($lastChildCode, -3);
                $nextChildNumber = (int) $childPart + 1;

                if ($nextChildNumber > 999) {
                    throw new Exception('Maximum of 999 children reached for parent '.$parentCode);
                }

                return $parentCode.str_pad($nextChildNumber, 3, '0', STR_PAD_LEFT);
            } else {
                return $parentCode.'001';
            }
        } else {
            if ($code !== null) {
                if ($code > 999) {
                    throw new Exception('Root code cannot exceed 999');
                }

                return str_pad($code, 3, '0', STR_PAD_LEFT);
            }

            $lastRootSubject = Subject::whereNull('parent_id')->orderBy('code', 'desc')->first();
            $nextRootNumber = 1;

            if ($lastRootSubject) {
                $nextRootNumber = (int) $lastRootSubject->code + 1;

                if ($nextRootNumber > 999) {
                    throw new Exception('Maximum of 999 root subjects reached');
                }
            }

            return str_pad($nextRootNumber, 3, '0', STR_PAD_LEFT);
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
        return ! is_null($this->parent_id);
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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereIsRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}

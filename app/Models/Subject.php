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

        static::creating(function ($subject) {

            $subject->company_id = session('active-company-id');

            // Generate code if not provided
            if (empty($subject->code)) {
                if (!empty($subject->parent_id)) {
                    $parentSubject = Subject::find($subject->parent_id);

                    if ($parentSubject->hasChildren()) {
                        // Get last child code and increment it
                        $lastChildCode = $parentSubject->children()->orderBy('code', 'desc')->first()->code;
                        $subject->code = str_pad((int) $lastChildCode + 1, strlen($lastChildCode), '0', STR_PAD_LEFT);
                    } else {
                        // Create first child code based on parent's code
                        $firstChildBase = $parentSubject->code . '000';  // Create initial child code format
                        $subject->code = str_pad((int) $firstChildBase + 1, strlen($firstChildBase), '0', STR_PAD_LEFT);
                    }
                } else {
                    // Handle root-level subjects (no parent)
                    $lastRootSubject = Subject::whereNull('parent_id')->orderBy('code', 'desc')->first();
                    $baseCode = $lastRootSubject->code ?? '000';  // Default for first root subject
                    $subject->code = str_pad((int) $baseCode + 1, strlen($baseCode), '0', STR_PAD_LEFT);
                }
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
}

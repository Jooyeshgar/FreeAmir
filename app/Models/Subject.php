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

                    $lastCode = $parentSubject->children()->orderBy('code', 'desc')->first()->code ?? '000';
                } else {
                    $lastCode = '000';
                }
                $subject->code = str_pad((int) $lastCode + 1, strlen($lastCode), "0", STR_PAD_LEFT);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'title',
        'name',
        'path',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function attachBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

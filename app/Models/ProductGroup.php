<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    protected $fillable = [
        'code',
        'name',
        'buyId',
        'sellId',
    ];

    // Define relationships with other models (e.g., Subject)

    public function buySubject()
    {
        return $this->belongsTo(Subject::class, 'buyId');
    }

    public function sellSubject()
    {
        return $this->belongsTo(Subject::class, 'sellId');
    }

    // Define any other methods as needed
}

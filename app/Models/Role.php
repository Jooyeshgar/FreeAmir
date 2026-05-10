<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function getDisplayNameAttribute(): string
    {
        $key = 'roles.'.$this->name;
        $translated = __($key);

        return $translated === $key ? $this->name : $translated;
    }
}

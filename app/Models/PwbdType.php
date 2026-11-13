<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PwbdType extends Model
{
    protected $fillable = ['group_no', 'group_name', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

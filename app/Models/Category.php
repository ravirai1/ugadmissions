<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public const CHRISTIAN = 'CHRISTIAN';

    protected $fillable = ['code', 'name', 'parent_code', 'certificate_label', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

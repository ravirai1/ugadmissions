<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A CUET (NTA) test paper.
 */
class Subject extends Model
{
    public const LANGUAGE = 'language';
    public const DOMAIN = 'domain';
    public const GENERAL = 'general';

    protected $fillable = ['code', 'name', 'type', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public const TYPES = [
        'sqli' => 'SQL injection',
        'xss' => 'Script injection (XSS)',
        'traversal' => 'Path traversal / file access',
        'probe' => 'Probing for admin tools or files',
        'scanner' => 'Attack tool',
    ];

    protected $fillable = ['type', 'ip', 'method', 'path', 'field', 'sample', 'user_agent', 'caused_block'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'caused_block' => 'boolean'];
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(config('admissions.activity_log_days', 400)));
    }
}

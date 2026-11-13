<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintLog extends Model
{
    public const UPDATED_AT = null;

    public const ITEMS = ['application' => 'Application form', 'application-bulk' => 'Application forms (bulk)', 'receipt' => 'Fee receipt', 'document' => 'Document', 'call-letter' => 'Call letter'];

    protected $fillable = ['admin_id', 'application_id', 'item', 'meta', 'ip'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public static function record(string $item, ?Application $app = null, array $meta = []): void
    {
        static::create(['admin_id' => auth('admin')->id(), 'application_id' => $app?->id, 'item' => $item, 'meta' => $meta ?: null, 'ip' => request()?->ip()]);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NomusSalesSyncState extends Model
{
    protected $fillable = [
        'tenant_id',
        'sync_key',
        'last_synced_modified_at',
        'last_run_at',
        'last_success_at',
        'last_items_count',
        'last_created_count',
        'last_updated_count',
        'last_pages_count',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'last_synced_modified_at' => 'datetime',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_items_count' => 'integer',
            'last_created_count' => 'integer',
            'last_updated_count' => 'integer',
            'last_pages_count' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NomusProductComponent extends Model
{
    protected $fillable = [
        'tenant_id',
        'external_id',
        'parent_product_external_id',
        'component_product_external_id',
        'component_codigo',
        'component_nome',
        'quantity_required',
        'optional',
        'item_de_embarque',
        'natureza_consumo',
        'nomus_updated_at',
        'payload',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'external_id' => 'integer',
            'parent_product_external_id' => 'integer',
            'component_product_external_id' => 'integer',
            'quantity_required' => 'decimal:4',
            'optional' => 'boolean',
            'item_de_embarque' => 'boolean',
            'natureza_consumo' => 'integer',
            'nomus_updated_at' => 'datetime',
            'payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}


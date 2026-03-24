<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NomusSalesOrder extends Model
{
    protected $fillable = [
        'tenant_id',
        'external_id',
        'codigo_pedido',
        'empresa_external_id',
        'cliente_external_id',
        'data_emissao',
        'data_entrega_padrao',
        'nomus_created_at',
        'nomus_updated_at',
        'payload',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'external_id' => 'integer',
            'empresa_external_id' => 'integer',
            'cliente_external_id' => 'integer',
            'data_emissao' => 'date',
            'data_entrega_padrao' => 'date',
            'nomus_created_at' => 'datetime',
            'nomus_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(NomusSalesOrderItem::class, 'sales_order_id');
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NomusProduct extends Model
{
    protected $fillable = [
        'tenant_id',
        'external_id',
        'codigo',
        'nome',
        'descricao',
        'nome_tipo_produto',
        'ativo',
        'nomus_updated_at',
        'payload',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'external_id' => 'integer',
            'ativo' => 'boolean',
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


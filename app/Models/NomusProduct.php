<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NomusProduct extends Model
{
    use BelongsToTenant;
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

}


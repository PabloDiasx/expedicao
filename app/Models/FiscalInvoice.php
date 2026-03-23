<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalInvoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'external_id',
        'numero',
        'serie',
        'chave',
        'cnpj_emitente',
        'protocolo',
        'recibo',
        'ambiente',
        'finalidade',
        'status',
        'tipo_emissao',
        'tipo_operacao',
        'is_fornecedor',
        'usuario',
        'data_processamento',
        'hora_processamento',
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
            'ambiente' => 'integer',
            'finalidade' => 'integer',
            'status' => 'integer',
            'tipo_emissao' => 'integer',
            'tipo_operacao' => 'integer',
            'is_fornecedor' => 'boolean',
            'data_processamento' => 'date',
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
}

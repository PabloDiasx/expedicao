<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NomusSalesOrderItem extends Model
{
    use BelongsToTenant, SoftDeletes;
    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'item_code',
        'product_external_id',
        'quantity',
        'allocated_quantity',
        'item_status',
        'delivery_date',
        'product_code',
        'product_name',
        'payload',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'sales_order_id' => 'integer',
            'product_external_id' => 'integer',
            'quantity' => 'decimal:4',
            'allocated_quantity' => 'decimal:4',
            'item_status' => 'integer',
            'delivery_date' => 'date',
            'payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(NomusSalesOrder::class, 'sales_order_id');
    }
}

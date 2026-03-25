<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MontagemScanEvent extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'equipment_id',
        'equipment_model_id',
        'sales_order_id',
        'sales_order_item_id',
        'user_id',
        'barcode_input',
        'barcode_normalized',
        'device_identifier',
        'notes',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'equipment_id' => 'integer',
            'equipment_model_id' => 'integer',
            'sales_order_id' => 'integer',
            'sales_order_item_id' => 'integer',
            'user_id' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }

}


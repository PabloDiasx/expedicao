<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'is_active',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function fiscalInvoices(): HasMany
    {
        return $this->hasMany(FiscalInvoice::class);
    }

    public function invoiceSyncState(): HasOne
    {
        return $this->hasOne(InvoiceSyncState::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(NomusSalesOrder::class);
    }

    public function salesSyncStates(): HasMany
    {
        return $this->hasMany(NomusSalesSyncState::class);
    }
}

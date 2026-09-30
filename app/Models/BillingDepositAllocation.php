<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BillingDepositAllocation extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    public function depositInvoice() { return $this->belongsTo(BillingInvoice::class, 'deposit_invoice_id'); }
    public function finalInvoice() { return $this->belongsTo(BillingInvoice::class, 'final_invoice_id'); }
}

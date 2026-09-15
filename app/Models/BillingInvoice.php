<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class BillingInvoice extends Model {
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    public function items() { return $this->hasMany(BillingInvoiceItem::class, 'invoice_id'); }
    public function payments() { return $this->hasMany(BillingPayment::class, 'invoice_id'); }
}

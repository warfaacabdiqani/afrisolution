<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class BillingInvoice extends Model {
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    protected function casts(): array { return ['document_snapshot' => 'array']; }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function salonClient() { return $this->belongsTo(SalonClient::class); }
    public function items() { return $this->hasMany(BillingInvoiceItem::class, 'invoice_id'); }
    public function payments() { return $this->hasMany(BillingPayment::class, 'invoice_id'); }
    public function depositAllocations() { return $this->hasMany(BillingDepositAllocation::class, 'deposit_invoice_id'); }
}

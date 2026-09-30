<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class BillingPayment extends Model { use BelongsToTenant; protected $guarded = ['id', 'tenant_id']; public function invoice() { return $this->belongsTo(BillingInvoice::class, 'invoice_id'); } public function receipt() { return $this->hasOne(BillingReceipt::class, 'payment_id'); } public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); } public function reversal() { return $this->hasOne(self::class, 'reverses_payment_id'); } public function reversedPayment() { return $this->belongsTo(self::class, 'reverses_payment_id'); } }

<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class BillingPayment extends Model { use BelongsToTenant; protected $guarded = ['id', 'tenant_id']; public function invoice() { return $this->belongsTo(BillingInvoice::class, 'invoice_id'); } }

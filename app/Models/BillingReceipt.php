<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BillingReceipt extends Model
{
    use BelongsToTenant;

    protected $fillable = ['invoice_id', 'payment_id', 'number', 'snapshot'];

    protected function casts(): array { return ['snapshot' => 'array']; }

    public function invoice() { return $this->belongsTo(BillingInvoice::class); }
    public function payment() { return $this->belongsTo(BillingPayment::class); }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Issued receipts are immutable.'));
        static::deleting(fn () => throw new \LogicException('Issued receipts cannot be deleted.'));
    }
}

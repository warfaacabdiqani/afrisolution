<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DentalPlanItem extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    protected function casts(): array { return ['unit_price' => 'decimal:2', 'surfaces' => 'array', 'completed_at' => 'datetime']; }
    public function plan() { return $this->belongsTo(DentalPlan::class, 'plan_id'); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by')->select(['id', 'name']); }
    public function invoice() { return $this->hasOne(BillingInvoice::class, 'source_id')->where('source_type', 'dental_treatment'); }
}

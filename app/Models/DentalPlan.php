<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DentalPlan extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    protected function casts(): array { return ['tax_rate' => 'decimal:2', 'version' => 'integer', 'accepted_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    public function items() { return $this->hasMany(DentalPlanItem::class, 'plan_id')->orderBy('visit_number')->orderBy('id'); }
    public function patient() { return $this->belongsTo(Patient::class); }
}

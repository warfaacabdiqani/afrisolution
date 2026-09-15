<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class SalonAppointmentService extends Model {
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    public function service() { return $this->belongsTo(SalonService::class); }
}

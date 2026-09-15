<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class SalonStaffTimeOff extends Model {
    use BelongsToTenant;
    protected $table='salon_staff_time_off';
    protected $guarded=['id','tenant_id'];
    public function stylist() { return $this->belongsTo(SalonStaffProfile::class,'stylist_id'); }
}

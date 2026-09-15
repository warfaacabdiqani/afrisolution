<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class SalonStaffSchedule extends Model {
    use BelongsToTenant;
    protected $guarded=['id','tenant_id'];
    public function stylist() { return $this->belongsTo(SalonStaffProfile::class,'stylist_id'); }
    public function branch() { return $this->belongsTo(Branch::class); }
}

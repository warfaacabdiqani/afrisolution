<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class SalonService extends Model {
    use BelongsToTenant;
    protected $fillable = ['service_category_id','name','code','description','duration_minutes','price','requires_deposit','deposit_amount'];
    protected function casts(): array { return ['price'=>'decimal:2','deposit_amount'=>'decimal:2','requires_deposit'=>'boolean','duration_minutes'=>'integer']; }
    public function category() { return $this->belongsTo(ServiceCategory::class,'service_category_id'); }
    public function branches() { return $this->belongsToMany(Branch::class,'salon_service_branch')->withPivot('tenant_id'); }
    public function stylists() { return $this->belongsToMany(SalonStaffProfile::class,'salon_service_staff','service_id','salon_staff_profile_id')->withPivot('tenant_id'); }
    public function appointmentItems() { return $this->hasMany(SalonAppointmentService::class,'service_id'); }
}

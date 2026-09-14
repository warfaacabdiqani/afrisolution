<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class SalonStaffProfile extends Model {
    use BelongsToTenant;
    protected $fillable = ['user_id','display_name','title','bio','status','commission_type','commission_value'];
    protected function casts(): array { return ['commission_value'=>'decimal:2']; }
    public function user() { return $this->belongsTo(User::class); }
    public function branches() { return $this->belongsToMany(Branch::class,'salon_staff_branch')->withPivot('tenant_id'); }
    public function services() { return $this->belongsToMany(SalonService::class,'salon_service_staff','salon_staff_profile_id','service_id')->withPivot('tenant_id'); }
}

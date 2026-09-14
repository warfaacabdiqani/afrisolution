<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class SalonClient extends Model {
    use BelongsToTenant;
    protected $table = 'salon_clients';
    protected $fillable = ['first_name','middle_name','last_name','gender','date_of_birth','phone','email','address','notes','preferred_stylist_id'];
    protected function casts(): array { return ['date_of_birth'=>'date']; }
    public function getFullNameAttribute(): string { return implode(' ',array_filter([$this->first_name,$this->middle_name,$this->last_name])); }
    public function preferredStylist() { return $this->belongsTo(SalonStaffProfile::class,'preferred_stylist_id'); }
    public function branch() { return $this->belongsTo(Branch::class); }
}

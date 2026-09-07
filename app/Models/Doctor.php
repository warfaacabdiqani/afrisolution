<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class Doctor extends Model {
    use BelongsToTenant;
    protected $fillable = ['first_name', 'middle_name', 'last_name', 'gender', 'phone', 'email', 'license_number', 'qualification', 'consultation_fee', 'notes', 'availability_status'];
    protected function casts(): array { return ['consultation_fee' => 'decimal:2']; }
    public function branches() { return $this->belongsToMany(Branch::class, 'doctor_branch')->withPivot('tenant_id'); }
    public function specialties() { return $this->belongsToMany(Specialty::class, 'doctor_specialty')->withPivot('tenant_id'); }
    public function schedules() { return $this->hasMany(DoctorSchedule::class); }
    public function leaves() { return $this->hasMany(DoctorLeave::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function getFullNameAttribute(): string { return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])); }
}

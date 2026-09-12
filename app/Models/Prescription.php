<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class Prescription extends Model {
    use BelongsToTenant;
    protected $guarded = ['id','tenant_id'];
    protected function casts(): array { return ['prescription_date'=>'date','cancelled_at'=>'datetime']; }
    public function items() { return $this->hasMany(PrescriptionItem::class); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function doctor() { return $this->belongsTo(Doctor::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function appointment() { return $this->belongsTo(Appointment::class); }
}

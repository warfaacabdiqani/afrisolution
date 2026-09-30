<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class SalonAppointment extends Model {
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    public function client() { return $this->belongsTo(SalonClient::class); }
    public function stylist() { return $this->belongsTo(SalonStaffProfile::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function items() { return $this->hasMany(SalonAppointmentService::class, 'appointment_id'); }
    public function invoice() { return $this->hasOne(BillingInvoice::class, 'source_id')->where('source_type', 'salon_appointment'); }
    public function depositInvoice() { return $this->hasOne(BillingInvoice::class, 'source_id')->where('source_type', 'salon_appointment_deposit'); }
}

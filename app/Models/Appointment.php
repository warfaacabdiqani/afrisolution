<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id', 'appointment_number', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_walk_in' => 'boolean'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function type()
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

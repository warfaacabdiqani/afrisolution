<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id', 'patient_number', 'registration_branch_id', 'created_by', 'updated_by', 'registered_at', 'archived_at'];
    protected function casts(): array { return ['date_of_birth' => 'date', 'registered_at' => 'datetime', 'archived_at' => 'datetime']; }
    public function allergies() { return $this->hasMany(PatientAllergy::class); }
    public function conditions() { return $this->hasMany(PatientCondition::class); }
    public function documents() { return $this->hasMany(PatientDocument::class); }
    public function registrationBranch() { return $this->belongsTo(Branch::class, 'registration_branch_id'); }
    public function getFullNameAttribute(): string { return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])); }
}

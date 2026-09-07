<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'status', 'price', 'currency', 'billing_period', 'trial_days',
        'branch_limit', 'member_limit', 'doctor_limit', 'patient_limit', 'storage_limit_gb',
        'appointment_limit', 'invoice_limit', 'features',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'features' => 'array'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}

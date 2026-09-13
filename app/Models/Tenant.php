<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'status', 'timezone', 'business_type_id'];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}

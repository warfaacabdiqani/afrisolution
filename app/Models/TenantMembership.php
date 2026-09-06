<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantMembership extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'role', 'status'];
}

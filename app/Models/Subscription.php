<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToTenant;

    protected $fillable = ['plan_id', 'status', 'trial_ends_at', 'branch_limit', 'member_limit'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime'];
    }
}

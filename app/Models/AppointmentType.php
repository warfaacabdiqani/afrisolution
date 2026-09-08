<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AppointmentType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'default_duration', 'color_key', 'status'];
}

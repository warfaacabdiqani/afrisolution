<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class DoctorSchedule extends Model { use BelongsToTenant; protected $guarded = ['id','tenant_id','doctor_id']; protected function casts(): array { return ['is_available' => 'boolean']; } }

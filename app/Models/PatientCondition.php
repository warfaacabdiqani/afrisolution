<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PatientCondition extends Model {
    use BelongsToTenant;
    protected $fillable = ['condition_name', 'diagnosed_date', 'status', 'notes', 'recorded_by'];
    public function patient() { return $this->belongsTo(Patient::class); }
}

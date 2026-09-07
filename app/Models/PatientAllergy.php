<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PatientAllergy extends Model {
    use BelongsToTenant;
    protected $fillable = ['allergen', 'reaction', 'severity', 'status', 'notes', 'recorded_by'];
    public function patient() { return $this->belongsTo(Patient::class); }
}

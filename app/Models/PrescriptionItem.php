<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PrescriptionItem extends Model {
    use BelongsToTenant;
    protected $guarded = ['id','tenant_id'];
    public function medication() { return $this->belongsTo(Medication::class); }
    public function prescription() { return $this->belongsTo(Prescription::class); }
}

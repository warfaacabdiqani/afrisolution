<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PatientDocument extends Model {
    use BelongsToTenant;
    protected $fillable = ['title', 'document_type', 'description', 'path', 'mime', 'extension', 'size', 'recorded_by', 'archived_at'];
    protected $hidden = ['path', 'tenant_id'];
    public function patient() { return $this->belongsTo(Patient::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}

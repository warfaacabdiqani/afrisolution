<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DentalFinding extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    protected function casts(): array { return ['surfaces' => 'array', 'voided_at' => 'datetime']; }
    public function author() { return $this->belongsTo(User::class, 'recorded_by')->select(['id', 'name']); }
}

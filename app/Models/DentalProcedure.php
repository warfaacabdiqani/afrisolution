<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DentalProcedure extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    protected function casts(): array { return ['price' => 'decimal:2', 'active' => 'boolean']; }
}

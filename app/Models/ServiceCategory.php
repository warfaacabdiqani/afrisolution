<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ServiceCategory extends Model {
    use BelongsToTenant;
    protected $fillable = ['name','description','status','sort_order'];
}

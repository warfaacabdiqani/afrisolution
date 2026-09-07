<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class PlatformRole extends Model
{
    protected $fillable=['name','slug','description'];
    protected function casts(): array { return ['is_system'=>'boolean']; }
    public function permissions(): BelongsToMany { return $this->belongsToMany(PlatformPermission::class, 'platform_permission_role'); }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class, 'platform_role_user'); }
}

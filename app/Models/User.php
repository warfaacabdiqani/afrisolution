<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\VerifyAfrisoEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            if ($user->isDirty('email')) $user->email_verified_at = null;
        });
        static::updated(function (self $user): void {
            if ($user->wasChanged('email')) {
                $id = $user->id;
                DB::afterCommit(function () use ($id): void {
                    $updated = self::find($id);
                    if ($updated && !$updated->hasVerifiedEmail()) $updated->sendEmailVerificationNotification();
                });
            }
        });
    }

    protected $attributes = [
        'is_platform_admin' => false,
        'status' => 'active',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function platformRoles(): BelongsToMany
    {
        return $this->belongsToMany(PlatformRole::class, 'platform_role_user');
    }

    public function hasPlatformPermission(string $permission): bool
    {
        if (! $this->is_platform_admin || $this->status !== 'active') return false;
        return $this->platformRoles()->whereHas('permissions', fn ($query) => $query->where('name', $permission))->exists();
    }

    public function sendEmailVerificationNotification(): void
    {
        app(\App\Services\PlatformMailConfigurator::class)->apply();
        $this->notify(new VerifyAfrisoEmail());
    }
}

<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role_id', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Central permission check used by the `permission:` middleware and controllers.
     * Super Administrator (is_system role with no permission rows attached, or a
     * wildcard row "*") always passes — everyone else needs the exact key.
     */
    public function can($abilities, $arguments = []): bool
    {
        if (is_string($abilities) && str_contains($abilities, '.')) {
            return $this->hasPermission($abilities);
        }

        return parent::can($abilities, $arguments);
    }

    public function hasPermission(string $key): bool
    {
        if (! $this->role) {
            return false;
        }

        if ($this->role->name === 'super_admin') {
            return true;
        }

        return $this->role->hasPermission($key);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

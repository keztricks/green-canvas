<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    const ROLE_ADMIN = 'admin';
    const ROLE_CANVASSER = 'canvasser';
    const ROLE_WARD_ADMIN = 'ward_admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Mutator to ensure email is always stored in lowercase.
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is a canvasser.
     */
    public function isCanvasser(): bool
    {
        return $this->role === self::ROLE_CANVASSER;
    }

    /**
     * Check if user is a ward admin.
     */
    public function isWardAdmin(): bool
    {
        return $this->role === self::ROLE_WARD_ADMIN;
    }

    /**
     * Check if user can access exports (admins and ward admins only).
     */
    public function canAccessExports(): bool
    {
        return $this->isAdmin() || $this->isWardAdmin();
    }

    /**
     * Get the wards assigned to this user.
     */
    public function wards()
    {
        return $this->belongsToMany(Ward::class);
    }

    /**
     * Get the export schedules for this user.
     */
    public function exportSchedules()
    {
        return $this->hasMany(UserWardExportSchedule::class);
    }

    /**
     * Check if user has access to a specific ward.
     */
    public function hasAccessToWard($wardId): bool
    {
        // Admins have access to all wards
        if ($this->isAdmin()) {
            return true;
        }
        
        // Ward admins and canvassers only have access to their assigned wards
        return $this->wards()->where('wards.id', $wardId)->exists();
    }

    /**
     * Get available roles.
     */
    public static function roles(): array
    {
        return [
            self::ROLE_CANVASSER => 'Canvasser',
            self::ROLE_WARD_ADMIN => 'Ward Admin',
            self::ROLE_ADMIN => 'Admin',
        ];
    }
}

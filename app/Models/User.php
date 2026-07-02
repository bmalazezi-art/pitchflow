<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\QueuedVerifyEmail;
use App\Support\EmployeePermissions;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property UserRole $role
 * @property string $preferred_language
 * @property-read Organization|null $organization
 */
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'email', 'password', 'role', 'phone',
        'preferred_language', 'email_verified_at', 'last_login_at', 'status',
        'permissions', 'invited_at', 'invitation_accepted_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'permissions' => 'array',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignedFields(): BelongsToMany
    {
        return $this->belongsToMany(FootballField::class, 'employee_field_assignments')->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::Employee;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasEmployeePermission(string $permission): bool
    {
        if (! $this->isEmployee() || ! $this->isActive()) {
            return false;
        }

        return in_array($permission, $this->permissions ?? EmployeePermissions::all(), true);
    }

    public function preferredLocale(): string
    {
        return $this->preferred_language;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }
}

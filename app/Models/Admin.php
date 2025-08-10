<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     */
    protected $table = 'admins';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'permissions',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'notes',
        'created_by',
        'ip_restriction_enabled',
        'allowed_ips',
        'two_factor_enabled',
        'two_factor_secret',
        'login_notifications_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'ip_restriction_enabled' => 'boolean',
        'allowed_ips' => 'array',
        'two_factor_enabled' => 'boolean',
        'login_notifications_enabled' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically set created_by when creating a new admin
        static::creating(function ($admin) {
            if (auth()->guard('admin')->check()) {
                $admin->created_by = auth()->guard('admin')->id();
            }
        });
    }

    /**
     * Get the admin who created this admin.
     */
    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the admins created by this admin.
     */
    public function createdAdmins()
    {
        return $this->hasMany(Admin::class, 'created_by');
    }

    /**
     * Get the activities for this admin.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(AdminActivity::class);
    }

    /**
     * Get recent activities for this admin.
     */
    public function getRecentActivities(int $limit = 5)
    {
        return $this->activities()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Log an activity for this admin.
     */
    public function logActivity(
        string $action,
        string $description,
        string $type,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AdminActivity {
        return AdminActivity::log(
            $this->id,
            $action,
            $description,
            $type,
            $metadata,
            $ipAddress,
            $userAgent
        );
    }

    /**
     * Check if admin has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'super_admin') {
            return true; // Super admin has all permissions
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions);
    }

    /**
     * Check if admin has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if admin has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Grant a permission to the admin.
     */
    public function grantPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    /**
     * Revoke a permission from the admin.
     */
    public function revokePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->permissions = array_values($permissions);
        $this->save();
    }

    /**
     * Check if admin is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if admin is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Update last login information.
     */
    public function updateLastLogin(string $ipAddress): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);
    }

    /**
     * Check if IP address is allowed for this admin.
     */
    public function isIpAllowed(string $ipAddress): bool
    {
        // If IP restriction is not enabled, allow all IPs
        if (!$this->ip_restriction_enabled) {
            return true;
        }

        // If no allowed IPs are set, deny access
        if (empty($this->allowed_ips)) {
            return false;
        }

        // Check if the IP is in the allowed list
        return in_array($ipAddress, $this->allowed_ips);
    }

    /**
     * Add an IP address to the allowed list.
     */
    public function addAllowedIp(string $ipAddress): void
    {
        $allowedIps = $this->allowed_ips ?? [];
        if (!in_array($ipAddress, $allowedIps)) {
            $allowedIps[] = $ipAddress;
            $this->update(['allowed_ips' => $allowedIps]);
        }
    }

    /**
     * Remove an IP address from the allowed list.
     */
    public function removeAllowedIp(string $ipAddress): void
    {
        $allowedIps = $this->allowed_ips ?? [];
        $allowedIps = array_filter($allowedIps, fn($ip) => $ip !== $ipAddress);
        $this->update(['allowed_ips' => array_values($allowedIps)]);
    }

    /**
     * Enable IP restriction with initial IP addresses.
     */
    public function enableIpRestriction(array $ipAddresses = []): void
    {
        $this->update([
            'ip_restriction_enabled' => true,
            'allowed_ips' => $ipAddresses
        ]);
    }

    /**
     * Disable IP restriction.
     */
    public function disableIpRestriction(): void
    {
        $this->update([
            'ip_restriction_enabled' => false,
            'allowed_ips' => null
        ]);
    }

    /**
     * Get admin's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get admin's role display name.
     */
    public function getRoleDisplayAttribute(): string
    {
        return match($this->role) {
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'moderator' => 'Moderator',
            default => ucfirst($this->role),
        };
    }

    /**
     * Scope to get only active admins.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get admins by role.
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Get all available permissions.
     */
    public static function getAvailablePermissions(): array
    {
        return [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.suspend',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'analytics.view',
            'settings.view',
            'settings.edit',
            'admins.view',
            'admins.create',
            'admins.edit',
            'admins.delete',
            'security.view',
            'security.manage',
            'logs.view',
            'logs.clear',
        ];
    }

    /**
     * Get default permissions for a role.
     */
    public static function getDefaultPermissions(string $role): array
    {
        return match($role) {
            'super_admin' => self::getAvailablePermissions(),
            'admin' => [
                'users.view',
                'users.edit',
                'users.suspend',
                'products.view',
                'products.edit',
                'analytics.view',
                'settings.view',
                'logs.view',
            ],
            'moderator' => [
                'users.view',
                'products.view',
                'analytics.view',
            ],
            default => [],
        };
    }
}
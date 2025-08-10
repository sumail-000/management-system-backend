<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'action',
        'description',
        'type',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the admin that owns the activity.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Create a new activity record for an admin
     */
    public static function log(
        int $adminId,
        string $action,
        string $description,
        string $type,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'admin_id' => $adminId,
            'action' => $action,
            'description' => $description,
            'type' => $type,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Get recent activities for an admin
     */
    public static function getRecentForAdmin(int $adminId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('admin_id', $adminId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old activities (keep only last 50 per admin)
     */
    public static function cleanupOldActivities(int $adminId, int $keepCount = 50): void
    {
        $activities = self::where('admin_id', $adminId)
            ->orderBy('created_at', 'desc')
            ->skip($keepCount)
            ->pluck('id');

        if ($activities->isNotEmpty()) {
            self::whereIn('id', $activities)->delete();
        }
    }

    /**
     * Activity type constants
     */
    const TYPE_LOGIN = 'login';
    const TYPE_PROFILE = 'profile';
    const TYPE_SECURITY = 'security';
    const TYPE_SYSTEM = 'system';

    /**
     * Activity action constants
     */
    const ACTION_LOGIN_SUCCESS = 'login_success';
    const ACTION_LOGIN_FAILED = 'login_failed';
    const ACTION_LOGIN_BLOCKED = 'login_blocked';
    const ACTION_PROFILE_UPDATE = 'profile_update';
    const ACTION_AVATAR_UPDATE = 'avatar_update';
    const ACTION_PASSWORD_CHANGE = 'password_change';
    const ACTION_IP_RESTRICTION_ENABLED = 'ip_restriction_enabled';
    const ACTION_IP_RESTRICTION_DISABLED = 'ip_restriction_disabled';
    const ACTION_LOGIN_NOTIFICATIONS_ENABLED = 'login_notifications_enabled';
    const ACTION_LOGIN_NOTIFICATIONS_DISABLED = 'login_notifications_disabled';
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * QR Code Analytics Model
 * 
 * Tracks QR code creation and deletion events for analytics purposes
 * 
 * @property int $id
 * @property int $user_id
 * @property string $event_type
 * @property int|null $qr_code_id
 * @property string|null $qr_code_type
 * @property array|null $metadata
 * @property Carbon $event_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class QrCodeAnalytics extends Model
{
    use HasFactory;

    protected $table = 'qr_code_analytics';

    protected $fillable = [
        'user_id',
        'event_type',
        'qr_code_id',
        'qr_code_type',
        'metadata',
        'event_date',
    ];

    protected $casts = [
        'metadata' => 'json',
        'event_date' => 'datetime',
    ];

    /**
     * Event types constants
     */
    const EVENT_CREATED = 'created';
    const EVENT_DELETED = 'deleted';

    /**
     * QR Code types constants
     */
    const TYPE_PRODUCT = 'product';
    const TYPE_URL = 'url';
    const TYPE_CUSTOM = 'custom';

    /**
     * Get the user that owns the analytics record
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the QR code associated with this analytics record
     */
    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    /**
     * Track QR code creation event
     */
    public static function trackCreation(int $userId, int $qrCodeId, string $qrCodeType, array $metadata = []): void
    {
        self::create([
            'user_id' => $userId,
            'event_type' => self::EVENT_CREATED,
            'qr_code_id' => $qrCodeId,
            'qr_code_type' => $qrCodeType,
            'metadata' => $metadata,
            'event_date' => now(),
        ]);
    }

    /**
     * Track QR code deletion event
     */
    public static function trackDeletion(int $userId, int $qrCodeId, string $qrCodeType, array $metadata = []): void
    {
        self::create([
            'user_id' => $userId,
            'event_type' => self::EVENT_DELETED,
            'qr_code_id' => $qrCodeId,
            'qr_code_type' => $qrCodeType,
            'metadata' => $metadata,
            'event_date' => now(),
        ]);
    }

    /**
     * Get QR code creation analytics for a user
     */
    public static function getCreationAnalytics(int $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $analytics = self::where('user_id', $userId)
            ->where('event_type', self::EVENT_CREATED)
            ->where('event_date', '>=', $startDate)
            ->selectRaw('DATE(event_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $analytics->toArray();
    }

    /**
     * Get QR code deletion analytics for a user
     */
    public static function getDeletionAnalytics(int $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $analytics = self::where('user_id', $userId)
            ->where('event_type', self::EVENT_DELETED)
            ->where('event_date', '>=', $startDate)
            ->selectRaw('DATE(event_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $analytics->toArray();
    }

    /**
     * Get total QR codes created by user
     */
    public static function getTotalCreated(int $userId): int
    {
        return self::where('user_id', $userId)
            ->where('event_type', self::EVENT_CREATED)
            ->count();
    }

    /**
     * Get total QR codes deleted by user
     */
    public static function getTotalDeleted(int $userId): int
    {
        return self::where('user_id', $userId)
            ->where('event_type', self::EVENT_DELETED)
            ->count();
    }

    /**
     * Get QR code creation/deletion trends
     */
    public static function getTrends(int $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $trends = self::where('user_id', $userId)
            ->where('event_date', '>=', $startDate)
            ->selectRaw('DATE(event_date) as date, event_type, COUNT(*) as count')
            ->groupBy('date', 'event_type')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        $result = [];
        foreach ($trends as $date => $events) {
            $created = $events->where('event_type', self::EVENT_CREATED)->first()?->count ?? 0;
            $deleted = $events->where('event_type', self::EVENT_DELETED)->first()?->count ?? 0;
            
            $result[] = [
                'date' => $date,
                'created' => $created,
                'deleted' => $deleted,
                'net_change' => $created - $deleted
            ];
        }

        return $result;
    }
}
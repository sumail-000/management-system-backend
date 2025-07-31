<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property string $url_slug
 * @property string|null $image_path
 * @property int $scan_count
 * @property \Illuminate\Support\Carbon|null $last_scanned_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Product $product
 */
class QrCode extends Model
{
    use HasFactory;

    protected $table = 'qr_codes';

    protected $fillable = [
        'product_id',
        'url_slug',
        'image_path',
        'scan_count',
        'last_scanned_at',
        'unique_code',
        'is_premium',
        'analytics_data',
    ];

    protected $casts = [
        'scan_count' => 'integer',
        'last_scanned_at' => 'datetime',
        'is_premium' => 'boolean',
        'analytics_data' => 'json',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    /**
     * Generate unique QR code for premium users
     */
    public function generateUniqueCode(): string
    {
        if (!$this->unique_code) {
            $this->unique_code = 'QR-' . strtoupper(uniqid()) . '-' . $this->product_id;
            $this->save();
        }
        return $this->unique_code;
    }

    /**
     * Track scan with analytics data
     */
    public function trackScan(array $analyticsData = []): void
    {
        $this->increment('scan_count');
        $this->last_scanned_at = now();
        
        // Store analytics data for premium users
        if ($this->is_premium) {
            $currentAnalytics = $this->analytics_data ?? [];
            $currentAnalytics['scans'][] = [
                'timestamp' => now()->toISOString(),
                'data' => $analyticsData
            ];
            $this->analytics_data = $currentAnalytics;
        }
        
        $this->save();
    }

    /**
     * Get scan analytics for the QR code
     */
    public function getScanAnalytics(): array
    {
        if (!$this->is_premium || !$this->analytics_data) {
            return [
                'total_scans' => $this->scan_count,
                'last_scanned' => $this->last_scanned_at,
                'premium_analytics' => false
            ];
        }

        $scans = $this->analytics_data['scans'] ?? [];
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        return [
            'total_scans' => $this->scan_count,
            'last_scanned' => $this->last_scanned_at,
            'premium_analytics' => true,
            'scans_today' => collect($scans)->filter(function ($scan) use ($today) {
                return $today->lte(\Carbon\Carbon::parse($scan['timestamp']));
            })->count(),
            'scans_this_week' => collect($scans)->filter(function ($scan) use ($thisWeek) {
                return $thisWeek->lte(\Carbon\Carbon::parse($scan['timestamp']));
            })->count(),
            'scans_this_month' => collect($scans)->filter(function ($scan) use ($thisMonth) {
                return $thisMonth->lte(\Carbon\Carbon::parse($scan['timestamp']));
            })->count(),
            'recent_scans' => collect($scans)->sortByDesc('timestamp')->take(10)->values()->all()
        ];
    }

    /**
     * Check if QR code belongs to premium user
     */
    public function isPremiumQR(): bool
    {
        return $this->is_premium && $this->product->user->membership_plan->name !== 'Basic';
    }
}
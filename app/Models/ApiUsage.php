<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ApiUsage extends Model
{
    protected $table = 'api_usage';

    protected $fillable = [
        'user_id',
        'api_provider',
        'api_service',
        'endpoint',
        'method',
        'request_data',
        'response_status',
        'response_metadata',
        'response_time',
        'request_size',
        'response_size',
        'ip_address',
        'user_agent',
        'success',
        'error_message',
        'cost',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_metadata' => 'array',
        'response_time' => 'decimal:3',
        'success' => 'boolean',
        'cost' => 'decimal:6',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that made the API call
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an API usage record
     */
    public static function logApiCall(array $data): self
    {
        return self::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'api_provider' => $data['api_provider'] ?? 'edamam',
            'api_service' => $data['api_service'],
            'endpoint' => $data['endpoint'],
            'method' => $data['method'] ?? 'GET',
            'request_data' => $data['request_data'] ?? null,
            'response_status' => $data['response_status'] ?? null,
            'response_metadata' => $data['response_metadata'] ?? null,
            'response_time' => $data['response_time'] ?? null,
            'request_size' => $data['request_size'] ?? null,
            'response_size' => $data['response_size'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'success' => $data['success'] ?? true,
            'error_message' => $data['error_message'] ?? null,
            'cost' => $data['cost'] ?? null,
        ]);
    }

    /**
     * Get API calls count for today
     */
    public static function getTodayCount(): int
    {
        return self::whereDate('created_at', today())->count();
    }

    /**
     * Get API calls count for yesterday
     */
    public static function getYesterdayCount(): int
    {
        return self::whereDate('created_at', today()->subDay())->count();
    }

    /**
     * Get API calls count for a specific date
     */
    public static function getCountForDate(Carbon $date): int
    {
        return self::whereDate('created_at', $date)->count();
    }

    /**
     * Get API calls count between dates
     */
    public static function getCountBetweenDates(Carbon $startDate, Carbon $endDate): int
    {
        return self::whereBetween('created_at', [$startDate, $endDate])->count();
    }

    /**
     * Get successful API calls count for today
     */
    public static function getSuccessfulTodayCount(): int
    {
        return self::whereDate('created_at', today())
            ->where('success', true)
            ->count();
    }

    /**
     * Get failed API calls count for today
     */
    public static function getFailedTodayCount(): int
    {
        return self::whereDate('created_at', today())
            ->where('success', false)
            ->count();
    }

    /**
     * Get API calls by provider
     */
    public static function getCountByProvider(string $provider, Carbon $startDate = null, Carbon $endDate = null): int
    {
        $query = self::where('api_provider', $provider);
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        return $query->count();
    }

    /**
     * Get API calls by service
     */
    public static function getCountByService(string $service, Carbon $startDate = null, Carbon $endDate = null): int
    {
        $query = self::where('api_service', $service);
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        return $query->count();
    }

    /**
     * Get average response time for today
     */
    public static function getAverageResponseTimeToday(): float
    {
        return self::whereDate('created_at', today())
            ->whereNotNull('response_time')
            ->avg('response_time') ?? 0.0;
    }

    /**
     * Get total cost for a period
     */
    public static function getTotalCost(Carbon $startDate = null, Carbon $endDate = null): float
    {
        $query = self::whereNotNull('cost');
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        return $query->sum('cost') ?? 0.0;
    }

    /**
     * Get API usage statistics for dashboard
     */
    public static function getDashboardStats(): array
    {
        $today = today();
        $yesterday = today()->subDay();
        
        return [
            'today_total' => self::getTodayCount(),
            'yesterday_total' => self::getYesterdayCount(),
            'today_successful' => self::getSuccessfulTodayCount(),
            'today_failed' => self::getFailedTodayCount(),
            'average_response_time' => self::getAverageResponseTimeToday(),
            'success_rate' => self::getTodayCount() > 0 
                ? (self::getSuccessfulTodayCount() / self::getTodayCount()) * 100 
                : 100,
        ];
    }

    /**
     * Get hourly API usage for today
     */
    public static function getHourlyUsageToday(): array
    {
        $data = self::whereDate('created_at', today())
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        // Fill missing hours with 0
        $hourlyData = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyData[$i] = $data[$i] ?? 0;
        }

        return $hourlyData;
    }

    /**
     * Get top API services by usage
     */
    public static function getTopServices(int $limit = 5): array
    {
        return self::selectRaw('api_service, COUNT(*) as count')
            ->whereDate('created_at', today())
            ->groupBy('api_service')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Clean up old API usage records
     */
    public static function cleanupOldRecords(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        return self::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * Scope for successful API calls
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope for failed API calls
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope for specific provider
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('api_provider', $provider);
    }

    /**
     * Scope for specific service
     */
    public function scopeService($query, string $service)
    {
        return $query->where('api_service', $service);
    }

    /**
     * Scope for today's records
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for yesterday's records
     */
    public function scopeYesterday($query)
    {
        return $query->whereDate('created_at', today()->subDay());
    }
}
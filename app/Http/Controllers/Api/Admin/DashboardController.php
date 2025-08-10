<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\BillingHistory;
use App\Models\AdminActivity;
use App\Models\ApiUsage;
use App\Services\ApiUsageTracker;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard metrics for the specified month/year
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
        ]);

        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        // Create date ranges for current and previous month
        $currentStart = Carbon::create($year, $month, 1)->startOfMonth();
        $currentEnd = Carbon::create($year, $month, 1)->endOfMonth();
        
        $previousStart = $currentStart->copy()->subMonth()->startOfMonth();
        $previousEnd = $currentStart->copy()->subMonth()->endOfMonth();

        // Cache key for metrics
        $cacheKey = "admin_dashboard_metrics_{$year}_{$month}";
        
        $metrics = Cache::remember($cacheKey, 300, function () use ($currentStart, $currentEnd, $previousStart, $previousEnd) {
            return [
                'total_users' => $this->getTotalUsersMetric($currentStart, $currentEnd, $previousStart, $previousEnd),
                'active_products' => $this->getActiveProductsMetric($currentStart, $currentEnd, $previousStart, $previousEnd),
                'monthly_revenue' => $this->getMonthlyRevenueMetric($currentStart, $currentEnd, $previousStart, $previousEnd),
                'api_calls_today' => $this->getApiCallsTodayMetric(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'period' => [
                'month' => $month,
                'year' => $year,
                'current_period' => [
                    'start' => $currentStart->toDateString(),
                    'end' => $currentEnd->toDateString(),
                ],
                'previous_period' => [
                    'start' => $previousStart->toDateString(),
                    'end' => $previousEnd->toDateString(),
                ]
            ]
        ]);
    }

    /**
     * Get total users metric with comparison
     */
    private function getTotalUsersMetric(Carbon $currentStart, Carbon $currentEnd, Carbon $previousStart, Carbon $previousEnd): array
    {
        // Current month users
        $currentUsers = User::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        
        // Previous month users
        $previousUsers = User::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        
        // Total users up to current month
        $totalUsers = User::where('created_at', '<=', $currentEnd)->count();
        
        // Calculate percentage change
        $percentageChange = $this->calculatePercentageChange($currentUsers, $previousUsers);
        
        return [
            'title' => 'Total Users',
            'value' => $totalUsers > 0 ? number_format($totalUsers) : '0',
            'current_period_count' => $currentUsers,
            'previous_period_count' => $previousUsers,
            'change' => $percentageChange['formatted'],
            'trend' => $percentageChange['trend'],
            'icon' => 'Users',
            'color' => 'blue'
        ];
    }

    /**
     * Get active products metric with comparison
     */
    private function getActiveProductsMetric(Carbon $currentStart, Carbon $currentEnd, Carbon $previousStart, Carbon $previousEnd): array
    {
        // Current month products
        $currentProducts = Product::whereBetween('created_at', [$currentStart, $currentEnd])
            ->whereNull('deleted_at')
            ->count();
        
        // Previous month products
        $previousProducts = Product::whereBetween('created_at', [$previousStart, $previousEnd])
            ->whereNull('deleted_at')
            ->count();
        
        // Total active products
        $totalProducts = Product::whereNull('deleted_at')->count();
        
        // Calculate percentage change
        $percentageChange = $this->calculatePercentageChange($currentProducts, $previousProducts);
        
        return [
            'title' => 'Active Products',
            'value' => $totalProducts > 0 ? number_format($totalProducts) : '0',
            'current_period_count' => $currentProducts,
            'previous_period_count' => $previousProducts,
            'change' => $percentageChange['formatted'],
            'trend' => $percentageChange['trend'],
            'icon' => 'Package',
            'color' => 'green'
        ];
    }

    /**
     * Get monthly revenue metric with comparison
     */
    private function getMonthlyRevenueMetric(Carbon $currentStart, Carbon $currentEnd, Carbon $previousStart, Carbon $previousEnd): array
    {
        // Current month revenue
        $currentRevenue = BillingHistory::whereBetween('created_at', [$currentStart, $currentEnd])
            ->where('status', 'paid')
            ->sum('amount');
        
        // Previous month revenue
        $previousRevenue = BillingHistory::whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('status', 'paid')
            ->sum('amount');
        
        // Ensure we have numeric values
        $currentRevenue = $currentRevenue ?: 0;
        $previousRevenue = $previousRevenue ?: 0;
        
        // Calculate percentage change
        $percentageChange = $this->calculatePercentageChange($currentRevenue, $previousRevenue);
        
        return [
            'title' => 'Monthly Revenue',
            'value' => '$' . number_format($currentRevenue, 2),
            'current_period_amount' => $currentRevenue,
            'previous_period_amount' => $previousRevenue,
            'change' => $percentageChange['formatted'],
            'trend' => $percentageChange['trend'],
            'icon' => 'DollarSign',
            'color' => 'purple'
        ];
    }

    /**
     * Get API calls today metric with comparison to yesterday
     */
    private function getApiCallsTodayMetric(): array
    {
        // Get real API usage data
        $todayCalls = ApiUsage::getTodayCount();
        $yesterdayCalls = ApiUsage::getYesterdayCount();
        
        // Calculate percentage change
        $percentageChange = $this->calculatePercentageChange($todayCalls, $yesterdayCalls);
        
        // Get additional stats for metadata
        $successfulToday = ApiUsage::getSuccessfulTodayCount();
        $failedToday = ApiUsage::getFailedTodayCount();
        $successRate = $todayCalls > 0 ? ($successfulToday / $todayCalls) * 100 : 100;
        
        return [
            'title' => 'API Calls Today',
            'value' => number_format($todayCalls),
            'today_count' => $todayCalls,
            'yesterday_count' => $yesterdayCalls,
            'successful_today' => $successfulToday,
            'failed_today' => $failedToday,
            'success_rate' => round($successRate, 1),
            'change' => $percentageChange['formatted'],
            'trend' => $percentageChange['trend'],
            'icon' => 'Activity',
            'color' => 'orange'
        ];
    }

    /**
     * Calculate percentage change between two values
     */
    private function calculatePercentageChange(float $current, float $previous): array
    {
        if ($previous == 0) {
            if ($current > 0) {
                return [
                    'formatted' => '+100%',
                    'trend' => 'up',
                    'raw' => 100
                ];
            }
            return [
                'formatted' => '0%',
                'trend' => 'up',
                'raw' => 0
            ];
        }
        
        $change = (($current - $previous) / $previous) * 100;
        $trend = $change >= 0 ? 'up' : 'down';
        $formatted = ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
        
        return [
            'formatted' => $formatted,
            'trend' => $trend,
            'raw' => $change
        ];
    }

    /**
     * Get detailed analytics for charts
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:7d,30d,90d,1y',
            'metric' => 'nullable|in:users,products,revenue,api_calls'
        ]);

        $period = $request->input('period', '30d');
        $metric = $request->input('metric', 'users');

        $data = $this->getAnalyticsData($metric, $period);

        return response()->json([
            'success' => true,
            'data' => $data,
            'period' => $period,
            'metric' => $metric
        ]);
    }

    /**
     * Get analytics data for specific metric and period
     */
    private function getAnalyticsData(string $metric, string $period): array
    {
        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 30
        };

        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        return match($metric) {
            'users' => $this->getUsersAnalytics($startDate, $endDate, $days),
            'products' => $this->getProductsAnalytics($startDate, $endDate, $days),
            'revenue' => $this->getRevenueAnalytics($startDate, $endDate, $days),
            'api_calls' => $this->getApiCallsAnalytics($startDate, $endDate, $days),
            default => []
        };
    }

    /**
     * Get users analytics data
     */
    private function getUsersAnalytics(Carbon $startDate, Carbon $endDate, int $days): array
    {
        $data = [];
        $interval = $days > 90 ? 'week' : 'day';
        
        if ($interval === 'day') {
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $count = User::whereDate('created_at', $date)->count();
                $data[] = [
                    'date' => $date->format('Y-m-d'),
                    'label' => $date->format('M j'),
                    'value' => $count
                ];
            }
        } else {
            // Weekly aggregation for longer periods
            $weeks = ceil($days / 7);
            for ($i = 0; $i < $weeks; $i++) {
                $weekStart = $startDate->copy()->addWeeks($i)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                $count = User::whereBetween('created_at', [$weekStart, $weekEnd])->count();
                $data[] = [
                    'date' => $weekStart->format('Y-m-d'),
                    'label' => $weekStart->format('M j'),
                    'value' => $count
                ];
            }
        }
        
        return $data;
    }

    /**
     * Get products analytics data
     */
    private function getProductsAnalytics(Carbon $startDate, Carbon $endDate, int $days): array
    {
        $data = [];
        $interval = $days > 90 ? 'week' : 'day';
        
        if ($interval === 'day') {
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $count = Product::whereDate('created_at', $date)->whereNull('deleted_at')->count();
                $data[] = [
                    'date' => $date->format('Y-m-d'),
                    'label' => $date->format('M j'),
                    'value' => $count
                ];
            }
        } else {
            $weeks = ceil($days / 7);
            for ($i = 0; $i < $weeks; $i++) {
                $weekStart = $startDate->copy()->addWeeks($i)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                $count = Product::whereBetween('created_at', [$weekStart, $weekEnd])
                    ->whereNull('deleted_at')->count();
                $data[] = [
                    'date' => $weekStart->format('Y-m-d'),
                    'label' => $weekStart->format('M j'),
                    'value' => $count
                ];
            }
        }
        
        return $data;
    }

    /**
     * Get revenue analytics data
     */
    private function getRevenueAnalytics(Carbon $startDate, Carbon $endDate, int $days): array
    {
        $data = [];
        $interval = $days > 90 ? 'week' : 'day';
        
        if ($interval === 'day') {
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $amount = BillingHistory::whereDate('created_at', $date)
                    ->where('status', 'paid')
                    ->sum('amount');
                $data[] = [
                    'date' => $date->format('Y-m-d'),
                    'label' => $date->format('M j'),
                    'value' => (float) $amount
                ];
            }
        } else {
            $weeks = ceil($days / 7);
            for ($i = 0; $i < $weeks; $i++) {
                $weekStart = $startDate->copy()->addWeeks($i)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                $amount = BillingHistory::whereBetween('created_at', [$weekStart, $weekEnd])
                    ->where('status', 'paid')
                    ->sum('amount');
                $data[] = [
                    'date' => $weekStart->format('Y-m-d'),
                    'label' => $weekStart->format('M j'),
                    'value' => (float) $amount
                ];
            }
        }
        
        return $data;
    }

    /**
     * Get API calls analytics data
     */
    private function getApiCallsAnalytics(Carbon $startDate, Carbon $endDate, int $days): array
    {
        $data = [];
        $interval = $days > 90 ? 'week' : 'day';
        
        if ($interval === 'day') {
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $count = ApiUsage::getCountForDate($date);
                $data[] = [
                    'date' => $date->format('Y-m-d'),
                    'label' => $date->format('M j'),
                    'value' => $count
                ];
            }
        } else {
            $weeks = ceil($days / 7);
            for ($i = 0; $i < $weeks; $i++) {
                $weekStart = $startDate->copy()->addWeeks($i)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                $count = ApiUsage::getCountBetweenDates($weekStart, $weekEnd);
                $data[] = [
                    'date' => $weekStart->format('Y-m-d'),
                    'label' => $weekStart->format('M j'),
                    'value' => $count
                ];
            }
        }
        
        return $data;
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealth(): JsonResponse
    {
        $health = [
            'api_response_time' => $this->getApiResponseTime(),
            'server_uptime' => $this->getServerUptime(),
            'database_status' => $this->getDatabaseStatus(),
            'error_rate' => $this->getErrorRate(),
        ];

        return response()->json([
            'success' => true,
            'data' => $health
        ]);
    }

    private function getApiResponseTime(): array
    {
        // Simulate API response time check
        $responseTime = rand(80, 200);
        return [
            'value' => $responseTime . 'ms',
            'status' => $responseTime < 300 ? 'healthy' : 'warning',
            'raw_value' => $responseTime
        ];
    }

    private function getServerUptime(): array
    {
        // Simulate server uptime
        $uptime = rand(998, 1000) / 10;
        return [
            'value' => $uptime . '%',
            'status' => $uptime > 99 ? 'healthy' : 'warning',
            'raw_value' => $uptime
        ];
    }

    private function getDatabaseStatus(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'value' => 'Healthy',
                'status' => 'healthy',
                'raw_value' => 1
            ];
        } catch (\Exception $e) {
            return [
                'value' => 'Error',
                'status' => 'error',
                'raw_value' => 0
            ];
        }
    }

    private function getErrorRate(): array
    {
        // Simulate error rate calculation
        $errorRate = rand(1, 20) / 100;
        return [
            'value' => $errorRate . '%',
            'status' => $errorRate < 1 ? 'healthy' : 'warning',
            'raw_value' => $errorRate
        ];
    }
}
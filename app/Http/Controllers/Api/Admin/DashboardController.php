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
     * Get dashboard metrics
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $metrics = [
            'total_users' => $this->getTotalUsersMetric(),
            'active_products' => $this->getActiveProductsMetric(),
            'monthly_revenue' => $this->getMonthlyRevenueMetric(),
            'api_calls_today' => $this->getApiCallsTodayMetric(),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get total users metric
     */
    private function getTotalUsersMetric(): array
    {
        // All-time total users, using DB facade to bypass any model scopes/issues
        $totalUsers = DB::table('users')->count();
        
        return [
            'title' => 'Total Users',
            'value' => number_format($totalUsers),
            'change' => 'N/A',
            'trend' => 'up',
            'icon' => 'Users',
            'color' => 'blue'
        ];
    }

    /**
     * Get active products metric
     */
    private function getActiveProductsMetric(): array
    {
        // All-time total active (not soft-deleted) products, using DB facade
        $totalProducts = DB::table('products')->whereNull('deleted_at')->count();
        
        return [
            'title' => 'Active Products',
            'value' => number_format($totalProducts),
            'change' => 'N/A',
            'trend' => 'up',
            'icon' => 'Package',
            'color' => 'green'
        ];
    }

    /**
     * Get monthly revenue metric for the current calendar month
     */
    private function getMonthlyRevenueMetric(): array
    {
        // Revenue for the current calendar month, using DB facade
        $currentRevenue = DB::table('billing_history')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->where('status', 'paid')
            ->sum('amount');
            
        $currentRevenue = $currentRevenue ?: 0;

        return [
            'title' => 'Monthly Revenue',
            'value' => '$' . number_format($currentRevenue, 2),
            'change' => '',
            'trend' => 'up',
            'icon' => 'DollarSign',
            'color' => 'purple'
        ];
    }

    /**
     * Get API calls today metric
     */
    private function getApiCallsTodayMetric(): array
    {
        // API calls for today, using DB facade
        $todayCalls = DB::table('api_usage')->whereDate('created_at', today())->count();
        
        return [
            'title' => 'API Calls Today',
            'value' => number_format($todayCalls),
            'change' => '',
            'trend' => 'up',
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

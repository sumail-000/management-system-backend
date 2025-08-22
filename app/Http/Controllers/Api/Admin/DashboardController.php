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
        // Define date ranges for calculations
        $currentEnd = now();
        $previousMonthEnd = now()->subMonth()->endOfMonth();

        $currentMonthStart = now()->startOfMonth();
        $previousMonthStart = now()->subMonth()->startOfMonth();

        $metrics = [
            'total_users' => $this->getTotalUsersMetric($currentEnd, $previousMonthEnd),
            'active_products' => $this->getActiveProductsMetric($currentEnd, $previousMonthEnd),
            'monthly_revenue' => $this->getMonthlyRevenueMetric($currentMonthStart, $currentEnd, $previousMonthStart, $previousMonthEnd),
            'api_calls_today' => $this->getApiCallsTodayMetric(),
            'user_distribution' => $this->getUserDistribution(),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get total users metric
     */
    private function getTotalUsersMetric(Carbon $currentEnd, Carbon $previousMonthEnd): array
    {
        // All-time total users up to now
        $currentTotalUsers = DB::table('users')->where('created_at', '<=', $currentEnd)->count();
        
        // All-time total users up to the end of last month
        $previousTotalUsers = DB::table('users')->where('created_at', '<=', $previousMonthEnd)->count();

        $percentageChange = $this->calculatePercentageChange($currentTotalUsers, $previousTotalUsers);
        
        return [
            'title' => 'Total Users',
            'value' => number_format($currentTotalUsers),
            'change' => $percentageChange['formatted'],
            'trend' => $percentageChange['trend'],
            'icon' => 'Users',
            'color' => 'blue'
        ];
    }

    /**
     * Get active products metric
     */
    private function getActiveProductsMetric(Carbon $currentEnd, Carbon $previousMonthEnd): array
    {
        // All-time total active products up to now
        $currentTotalProducts = DB::table('products')->where('created_at', '<=', $currentEnd)->whereNull('deleted_at')->count();
        
        // All-time total active products up to the end of last month
        $previousTotalProducts = DB::table('products')->where('created_at', '<=', $previousMonthEnd)->whereNull('deleted_at')->count();

        $percentageChange = $this->calculatePercentageChange($currentTotalProducts, $previousTotalProducts);

        return [
            'title' => 'Active Products',
            'value' => number_format($currentTotalProducts),
            'change' => $percentageChange['formatted'],
            'trend' => $percentageChange['trend'],
            'icon' => 'Package',
            'color' => 'green'
        ];
    }

    /**
     * Get monthly revenue metric for the current calendar month
     */
    private function getMonthlyRevenueMetric(Carbon $currentMonthStart, Carbon $currentEnd, Carbon $previousMonthStart, Carbon $previousMonthEnd): array
    {
        // Revenue for the current calendar month to date
        $currentRevenue = DB::table('billing_history')
            ->whereBetween('created_at', [$currentMonthStart, $currentEnd])
            ->where('status', 'paid')
            ->sum('amount');
            
        // Revenue for the previous full month
        $previousRevenue = DB::table('billing_history')
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->where('status', 'paid')
            ->sum('amount');

        $currentRevenue = $currentRevenue ?: 0;
        $previousRevenue = $previousRevenue ?: 0;

        $percentageChange = $this->calculatePercentageChange($currentRevenue, $previousRevenue);

        return [
            'title' => 'Monthly Revenue',
            'value' => '$' . number_format($currentRevenue, 2),
            'change' => $percentageChange['formatted'],
            'trend' => $percentageChange['trend'],
            'icon' => 'DollarSign',
            'color' => 'purple'
        ];
    }

    /**
     * Get API calls today metric
     */
    private function getApiCallsTodayMetric(): array
    {
        // API calls for today
        $todayCalls = DB::table('api_usage')->whereDate('created_at', today())->count();
        // API calls for yesterday
        $yesterdayCalls = DB::table('api_usage')->whereDate('created_at', today()->subDay())->count();

        $percentageChange = $this->calculatePercentageChange($todayCalls, $yesterdayCalls);
        
        return [
            'title' => 'API Calls Today',
            'value' => number_format($todayCalls),
            'change' => $percentageChange['formatted'],
            'trend' => $percentageChange['trend'],
            'icon' => 'Activity',
            'color' => 'orange'
        ];
    }

    /**
     * Get user distribution by membership plan
     */
    private function getUserDistribution(): array
    {
        $distribution = DB::table('users')
            ->join('membership_plans', 'users.membership_plan_id', '=', 'membership_plans.id')
            ->select('membership_plans.name', DB::raw('count(*) as value'))
            ->groupBy('membership_plans.name')
            ->get()
            ->map(function ($row) {
                // Assign colors based on plan name for consistency with frontend
                $colors = [
                    'Basic' => '#e2e8f0',
                    'Pro' => '#3b82f6',
                    'Enterprise' => '#8b5cf6',
                ];
                $row->color = $colors[$row->name] ?? '#6b7280';
                return $row;
            });

        return $distribution->toArray();
    }

    /**
     * Calculate percentage change between two values
     */
    private function calculatePercentageChange(float $current, float $previous): array
    {
        if ($current == $previous) {
            return [
                'formatted' => '0.0%',
                'trend' => 'neutral',
                'raw' => 0
            ];
        }

        if ($previous == 0) {
            return [
                'formatted' => '+100.0%',
                'trend' => 'up',
                'raw' => 100
            ];
        }
        
        $change = (($current - $previous) / abs($previous)) * 100;
        $trend = $change > 0 ? 'up' : 'down';
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
            'metric' => 'nullable|in:users,products,revenue,api_calls',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $metric = $request->input('metric', 'users');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
            $days = $startDate->diffInDays($endDate) + 1;

            $data = match($metric) {
                'users' => $this->getUsersAnalytics($startDate, $endDate, $days),
                'products' => $this->getProductsAnalytics($startDate, $endDate, $days),
                'revenue' => $this->getRevenueAnalytics($startDate, $endDate, $days),
                'api_calls' => $this->getApiCallsAnalytics($startDate, $endDate, $days),
                default => []
            };

            $period = 'custom';
        } else {
            $period = $request->input('period', '30d');
            $data = $this->getAnalyticsData($metric, $period);
        }

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
        $results = DB::table('users')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as value'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateString = $date->format('Y-m-d');
            $data[] = [
                'date' => $dateString,
                'label' => $date->format('M j'),
                'value' => $results->get($dateString)->value ?? 0,
            ];
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
        $results = DB::table('billing_history')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('sum(amount) as value'))
            ->where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateString = $date->format('Y-m-d');
            $data[] = [
                'date' => $dateString,
                'label' => $date->format('M j'),
                'value' => (float) ($results->get($dateString)->value ?? 0),
            ];
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
     * Subscription distribution by plan with revenue (supports custom range)
     */
    public function getSubscriptionDistribution(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $useRange = $request->filled('start_date') && $request->filled('end_date');
        $startDate = $useRange ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate = $useRange ? Carbon::parse($request->input('end_date'))->endOfDay() : null;

        // User counts by plan
        $userCountsQuery = DB::table('users')
            ->leftJoin('membership_plans', 'users.membership_plan_id', '=', 'membership_plans.id')
            ->when($useRange, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('users.created_at', [$startDate, $endDate]);
            })
            ->select(DB::raw("COALESCE(membership_plans.name, 'Unknown') as plan"), DB::raw('count(users.id) as count'))
            ->groupBy('plan');

        $userCounts = $userCountsQuery->get()->keyBy('plan');
        $totalUsers = $userCounts->sum('count');

        // Revenue by plan (paid only)
        $revenueQuery = DB::table('billing_history')
            ->leftJoin('membership_plans', 'billing_history.membership_plan_id', '=', 'membership_plans.id')
            ->where('billing_history.status', 'paid')
            ->when($useRange, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('billing_history.billing_date', [$startDate, $endDate]);
            })
            ->select(DB::raw("COALESCE(membership_plans.name, 'Unknown') as plan"), DB::raw('SUM(billing_history.amount) as revenue'))
            ->groupBy('plan');

        $revenue = $revenueQuery->get()->keyBy('plan');

        // Merge
        $plans = $userCounts->keys()->merge($revenue->keys())->unique();
        $result = [];
        foreach ($plans as $plan) {
            $count = (int) ($userCounts[$plan]->count ?? 0);
            $rev = (float) ($revenue[$plan]->revenue ?? 0);
            $percentage = $totalUsers > 0 ? round(($count / $totalUsers) * 100, 1) : 0.0;
            $result[] = [
                'plan' => $plan,
                'count' => $count,
                'percentage' => $percentage,
                'revenue' => $rev,
            ];
        }

        // Sort by count desc
        usort($result, fn($a, $b) => $b['count'] <=> $a['count']);

        return response()->json([
            'success' => true,
            'data' => $result,
            'total' => $totalUsers
        ]);
    }

    /**
     * User growth metrics for common periods (week, month, quarter, year)
     */
    public function getUserGrowth(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        // If a custom date range is provided, return growth for the selected range only
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = Carbon::parse($request->input('start_date'))->startOfDay();
            $end = Carbon::parse($request->input('end_date'))->endOfDay();

            $newUsers = DB::table('users')->whereBetween('created_at', [$start, $end])->count();

            // Churn: confirmed cancellations effective in period OR payment_status changed to cancelled in period
            $churnConfirmed = DB::table('users')
                ->where('cancellation_status', 'confirmed')
                ->whereNotNull('cancellation_effective_at')
                ->whereBetween('cancellation_effective_at', [$start, $end])
                ->count();

            $churnCancelled = DB::table('users')
                ->where('payment_status', 'cancelled')
                ->whereBetween('updated_at', [$start, $end])
                ->count();

            $churned = $churnConfirmed + $churnCancelled;

            return response()->json([
                'success' => true,
                'data' => [[
                    'period' => 'Selected Range',
                    'new' => $newUsers,
                    'churned' => $churned,
                    'net' => $newUsers - $churned,
                ]]
            ]);
        }

        // Default standardized periods
        $now = now();
        $periods = [
            'This Week' => [now()->startOfWeek(), $now->copy()->endOfDay()],
            'This Month' => [now()->startOfMonth(), $now->copy()->endOfDay()],
            'This Quarter' => [now()->firstOfQuarter()->startOfDay(), $now->copy()->endOfDay()],
            'This Year' => [now()->startOfYear(), $now->copy()->endOfDay()],
        ];

        $data = [];
        foreach ($periods as $label => [$start, $end]) {
            $newUsers = DB::table('users')->whereBetween('created_at', [$start, $end])->count();

            // Churn: confirmed cancellations effective in period OR payment_status changed to cancelled in period
            $churnConfirmed = DB::table('users')
                ->where('cancellation_status', 'confirmed')
                ->whereNotNull('cancellation_effective_at')
                ->whereBetween('cancellation_effective_at', [$start, $end])
                ->count();

            $churnCancelled = DB::table('users')
                ->where('payment_status', 'cancelled')
                ->whereBetween('updated_at', [$start, $end])
                ->count();

            $churned = $churnConfirmed + $churnCancelled;
            $data[] = [
                'period' => $label,
                'new' => $newUsers,
                'churned' => $churned,
                'net' => $newUsers - $churned,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * API usage by membership plan (supports custom range)
     */
    public function getApiUsageByPlan(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $useRange = $request->filled('start_date') && $request->filled('end_date');
        $startDate = $useRange ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate = $useRange ? Carbon::parse($request->input('end_date'))->endOfDay() : null;

        $query = DB::table('api_usage')
            ->join('users', 'api_usage.user_id', '=', 'users.id')
            ->leftJoin('membership_plans', 'users.membership_plan_id', '=', 'membership_plans.id')
            ->when($useRange, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('api_usage.created_at', [$startDate, $endDate]);
            })
            ->select(DB::raw("COALESCE(membership_plans.name, 'Unknown') as plan"), DB::raw('COUNT(api_usage.id) as count'))
            ->groupBy('plan');

        $rows = $query->get();
        $total = $rows->sum('count');
        $data = $rows->map(function ($row) use ($total) {
            return [
                'plan' => $row->plan,
                'count' => (int) $row->count,
                'percentage' => $total > 0 ? round(($row->count / $total) * 100, 1) : 0.0,
            ];
        })->sortByDesc('count')->values()->toArray();

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $total
        ]);
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

    /**
     * Feature usage analytics across core features (range-aware)
     */
    public function getFeatureUsage(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        // Determine current range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = Carbon::parse($request->input('start_date'))->startOfDay();
            $end = Carbon::parse($request->input('end_date'))->endOfDay();
        } else {
            $end = now()->endOfDay();
            $start = now()->subDays(30)->startOfDay();
        }
        $days = $start->diffInDays($end) + 1;
        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

        // Current counts
        $productCurrent = DB::table('products')->whereBetween('created_at', [$start, $end])->count();
        $labelsCurrent = DB::table('labels')->whereBetween('created_at', [$start, $end])->count();
        $qrCurrent = DB::table('qr_codes')->whereBetween('created_at', [$start, $end])->count();
        $nutritionCurrent = DB::table('api_usage')->where('api_service', 'nutrition_analysis')->whereBetween('created_at', [$start, $end])->count();
        $recipeSearchCurrent = DB::table('api_usage')->where('api_service', 'recipe_search')->whereBetween('created_at', [$start, $end])->count();
        // Category management approximated by new categories created in range
        $categoryCurrent = DB::table('categories')->whereBetween('created_at', [$start, $end])->count();

        // Previous counts
        $productPrev = DB::table('products')->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $labelsPrev = DB::table('labels')->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $qrPrev = DB::table('qr_codes')->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $nutritionPrev = DB::table('api_usage')->where('api_service', 'nutrition_analysis')->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $recipeSearchPrev = DB::table('api_usage')->where('api_service', 'recipe_search')->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $categoryPrev = DB::table('categories')->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $items = [
            [ 'feature' => 'Product Creation', 'current' => $productCurrent, 'prev' => $productPrev ],
            [ 'feature' => 'Label Generator', 'current' => $labelsCurrent, 'prev' => $labelsPrev ],
            [ 'feature' => 'QR Code Generator', 'current' => $qrCurrent, 'prev' => $qrPrev ],
            [ 'feature' => 'Nutrition Analysis', 'current' => $nutritionCurrent, 'prev' => $nutritionPrev ],
            [ 'feature' => 'Recipe Search', 'current' => $recipeSearchCurrent, 'prev' => $recipeSearchPrev ],
            [ 'feature' => 'Category Management', 'current' => $categoryCurrent, 'prev' => $categoryPrev ],
        ];

        $max = max(1, collect($items)->max('current'));

        $data = collect($items)->map(function ($row) use ($max) {
            $current = (int) $row['current'];
            $prev = (int) $row['prev'];
            // Normalize usage as percentage of max in current window
            $usage = round(($current / $max) * 100, 1);
            // Compute trend as percentage change vs previous window
            if ($current === $prev) {
                $trend = '0.0%';
            } elseif ($prev === 0) {
                $trend = $current > 0 ? '+100.0%' : '0.0%';
            } else {
                $delta = (($current - $prev) / abs($prev)) * 100;
                $trend = ($delta >= 0 ? '+' : '') . number_format($delta, 1) . '%';
            }
            return [
                'feature' => $row['feature'],
                'usage' => $usage,
                'trend' => $trend,
                'current' => $current,
                'previous' => $prev,
            ];
        })->sortByDesc('usage')->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days' => $days
            ]
        ]);
    }
}

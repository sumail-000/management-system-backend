<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ReportsController extends Controller
{
    private function parseDateRange(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        try {
            $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
        } catch (\Throwable $e) {
            $fromDate = now()->subDays(30)->startOfDay();
        }
        try {
            $toDate = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        } catch (\Throwable $e) {
            $toDate = now()->endOfDay();
        }

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->subDays(30)->startOfDay(), $toDate];
        }

        return [$fromDate, $toDate];
    }

    /**
     * GET /api/admin/reports/user-activity
     * Returns signups by day and active users by day within the range
     */
    public function userActivity(Request $request)
    {
        [$fromDate, $toDate] = $this->parseDateRange($request);

        // Signups by day
        $signups = DB::table('users')
            ->selectRaw("DATE(created_at) as date, COUNT(*) as count")
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Active users by day (last_active_at)
        $active = DB::table('users')
            ->selectRaw("DATE(last_active_at) as date, COUNT(*) as count")
            ->whereNotNull('last_active_at')
            ->whereBetween('last_active_at', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(last_active_at)'))
            ->orderBy('date')
            ->get();

        // Suspended users in range
        $suspended = DB::table('users')
            ->where('is_suspended', true)
            ->whereBetween('updated_at', [$fromDate, $toDate])
            ->count();

        return response()->json([
            'success' => true,
            'params' => ['from' => $fromDate->toDateString(), 'to' => $toDate->toDateString()],
            'signups_by_day' => $signups,
            'active_by_day' => $active,
            'suspended_count' => $suspended,
        ]);
    }

    /**
     * GET /api/admin/reports/revenue-summary
     * Returns revenue totals grouped by day and by plan
     */
    public function revenueSummary(Request $request)
    {
        [$fromDate, $toDate] = $this->parseDateRange($request);

        // Totals by day
        $daily = DB::table('billing_histories')
            ->selectRaw("DATE(billing_date) as date, SUM(amount) as total_amount, COUNT(*) as tx_count")
            ->whereBetween('billing_date', [$fromDate, $toDate])
            ->whereIn('status', ['paid','completed','succeeded'])
            ->groupBy(DB::raw('DATE(billing_date)'))
            ->orderBy('date')
            ->get();

        // Totals by plan
        $byPlan = DB::table('billing_histories as bh')
            ->join('membership_plans as mp', 'mp.id', '=', 'bh.membership_plan_id')
            ->selectRaw('mp.name as plan, SUM(bh.amount) as total_amount, COUNT(*) as tx_count')
            ->whereBetween('bh.billing_date', [$fromDate, $toDate])
            ->whereIn('bh.status', ['paid','completed','succeeded'])
            ->groupBy('mp.name')
            ->orderBy('total_amount', 'desc')
            ->get();

        // Payment method distribution
        $byMethod = DB::table('payment_methods')
            ->selectRaw('provider, brand, COUNT(*) as count')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('provider','brand')
            ->orderBy('count','desc')
            ->get();

        return response()->json([
            'success' => true,
            'params' => ['from' => $fromDate->toDateString(), 'to' => $toDate->toDateString()],
            'revenue_by_day' => $daily,
            'revenue_by_plan' => $byPlan,
            'payment_methods' => $byMethod,
        ]);
    }

    /**
     * GET /api/admin/reports/product-performance
     * Returns products created by day, status breakdown, top categories
     */
    public function productPerformance(Request $request)
    {
        [$fromDate, $toDate] = $this->parseDateRange($request);

        // Products created by day
        $created = DB::table('products')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Status breakdown
        $status = DB::table('products')
            ->selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('status')
            ->get();

        // Public vs private
        $visibility = DB::table('products')
            ->selectRaw('is_public, COUNT(*) as count')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('is_public')
            ->get();

        // Top categories (by count)
        $categories = DB::table('products')
            ->selectRaw('category as category, COUNT(*) as count')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('category')
            ->orderBy('count','desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'params' => ['from' => $fromDate->toDateString(), 'to' => $toDate->toDateString()],
            'created_by_day' => $created,
            'status_breakdown' => $status,
            'visibility' => $visibility,
            'top_categories' => $categories,
        ]);
    }

    /**
     * GET /api/admin/reports/api-usage
     * Returns API calls by day, by service, and error rate if available
     */
    public function apiUsage(Request $request)
    {
        [$fromDate, $toDate] = $this->parseDateRange($request);

        $byDay = DB::table('api_usage')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $byService = DB::table('api_usage')
            ->selectRaw('api_service as service, COUNT(*) as count')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('api_service')
            ->orderBy('count','desc')
            ->get();

        // If there's a status column, compute error rate; otherwise return null
        $errorRate = null;
        if (\Schema::hasColumn('api_usage', 'status')) {
            $total = DB::table('api_usage')->whereBetween('created_at', [$fromDate, $toDate])->count();
            $errors = DB::table('api_usage')->whereBetween('created_at', [$fromDate, $toDate])->where('status', '>=', 400)->count();
            $errorRate = $total > 0 ? round(($errors / $total) * 100, 2) : 0;
        }

        return response()->json([
            'success' => true,
            'params' => ['from' => $fromDate->toDateString(), 'to' => $toDate->toDateString()],
            'calls_by_day' => $byDay,
            'calls_by_service' => $byService,
            'error_rate_percent' => $errorRate,
        ]);
    }

    /**
     * GET /api/admin/reports/platform-growth
     * Returns cumulative users, paid conversions, churn in range
     */
    public function platformGrowth(Request $request)
    {
        [$fromDate, $toDate] = $this->parseDateRange($request);

        // New users in range by day
        $newUsers = DB::table('users')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Paid conversions: users with payment_status paid with subscription_started_at in range
        $paidConversions = DB::table('users')
            ->selectRaw('DATE(subscription_started_at) as date, COUNT(*) as count')
            ->where('payment_status', 'paid')
            ->whereNotNull('subscription_started_at')
            ->whereBetween('subscription_started_at', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(subscription_started_at)'))
            ->orderBy('date')
            ->get();

        // Churn: users cancelled with subscription_ends_at in range
        $churn = DB::table('users')
            ->selectRaw('DATE(subscription_ends_at) as date, COUNT(*) as count')
            ->where('payment_status', 'cancelled')
            ->whereNotNull('subscription_ends_at')
            ->whereBetween('subscription_ends_at', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(subscription_ends_at)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'params' => ['from' => $fromDate->toDateString(), 'to' => $toDate->toDateString()],
            'new_users_by_day' => $newUsers,
            'paid_conversions_by_day' => $paidConversions,
            'churn_by_day' => $churn,
        ]);
    }

    /**
     * GET /api/admin/reports/feature-adoption
     * Aggregates monthly feature usage (products, qr_codes, labels) from usages table within range
     */
    public function featureAdoption(Request $request)
    {
        [$fromDate, $toDate] = $this->parseDateRange($request);

        // Usages table stores month as YYYY-MM; build month range
        $startMonth = $fromDate->copy()->startOfMonth();
        $endMonth = $toDate->copy()->startOfMonth();

        $rows = DB::table('usages')
            ->select('month', DB::raw('SUM(products) as products'), DB::raw('SUM(qr_codes) as qr_codes'), DB::raw('SUM(labels) as labels'))
            ->whereBetween('month', [$startMonth->format('Y-m'), $endMonth->format('Y-m')])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'params' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'month_from' => $startMonth->format('Y-m'),
                'month_to' => $endMonth->format('Y-m'),
            ],
            'usage_by_month' => $rows,
        ]);
    }
}

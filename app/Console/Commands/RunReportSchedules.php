<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class RunReportSchedules extends Command
{
    protected $signature = 'reports:run-schedules';
    protected $description = 'Run due report schedules, generate datasets, and deliver to recipients';

    public function handle(): int
    {
        $now = now();
        $due = DB::table('report_schedules')
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')
            ->limit(50)
            ->get();

        foreach ($due as $schedule) {
            try {
                $this->info("Running schedule #{$schedule->id} ({$schedule->report_key})");
                $params = json_decode($schedule->params ?? '{}', true) ?: [];
                // Derive date range based on params or defaults
                [$from, $to] = $this->resolveRange($params);

                // Fetch dataset from reports controller via DB queries directly
                $data = $this->fetchDataset($schedule->report_key, $from, $to);

                // Serialize to the selected format
                [$filename, $content, $mime] = $this->serialize($schedule->report_key, $schedule->format, $data, $from, $to);

                // Save file
                $path = 'reports/' . $filename;
                Storage::disk('local')->put($path, $content);

                // Record generated report
                DB::table('generated_reports')->insert([
                    'report_key' => $schedule->report_key,
                    'format' => $schedule->format,
                    'params' => json_encode(['from' => $from->toDateString(), 'to' => $to->toDateString()]),
                    'status' => 'ready',
                    'file_path' => $path,
                    'admin_id' => $schedule->admin_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // TODO: email recipients with link (out of scope for now)

                // Compute next_run_at based on cadence
                $next = $this->computeNextRun($schedule->cadence, $now);
                DB::table('report_schedules')->where('id', $schedule->id)->update([
                    'last_run_at' => $now,
                    'next_run_at' => $next,
                    'updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to run report schedule', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Command::SUCCESS;
    }

    private function resolveRange(array $params): array
    {
        $from = isset($params['from']) ? Carbon::parse($params['from'])->startOfDay() : now()->subDays(7)->startOfDay();
        $to = isset($params['to']) ? Carbon::parse($params['to'])->endOfDay() : now()->endOfDay();
        if ($from->gt($to)) [$from, $to] = [$to->copy()->subDays(7)->startOfDay(), $to];
        return [$from, $to];
    }

    private function fetchDataset(string $key, Carbon $from, Carbon $to): array
    {
        // Reuse same queries as ReportsController
        if ($key === 'user_activity') {
            $signups = DB::table('users')->selectRaw('DATE(created_at) as date, COUNT(*) as count')->whereBetween('created_at', [$from, $to])->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();
            $active = DB::table('users')->selectRaw('DATE(last_active_at) as date, COUNT(*) as count')->whereNotNull('last_active_at')->whereBetween('last_active_at', [$from, $to])->groupBy(DB::raw('DATE(last_active_at)'))->orderBy('date')->get();
            $suspended = DB::table('users')->where('is_suspended', true)->whereBetween('updated_at', [$from, $to])->count();
            return compact('signups','active','suspended');
        }
        if ($key === 'revenue_summary') {
            $daily = DB::table('billing_histories')->selectRaw('DATE(billing_date) as date, SUM(amount) as total_amount, COUNT(*) as tx_count')->whereBetween('billing_date', [$from, $to])->whereIn('status', ['paid','completed','succeeded'])->groupBy(DB::raw('DATE(billing_date)'))->orderBy('date')->get();
            $byPlan = DB::table('billing_histories as bh')->join('membership_plans as mp', 'mp.id', '=', 'bh.membership_plan_id')->selectRaw('mp.name as plan, SUM(bh.amount) as total_amount, COUNT(*) as tx_count')->whereBetween('bh.billing_date', [$from, $to])->whereIn('bh.status', ['paid','completed','succeeded'])->groupBy('mp.name')->orderBy('total_amount','desc')->get();
            $byMethod = DB::table('payment_methods')->selectRaw('provider, brand, COUNT(*) as count')->whereBetween('created_at', [$from, $to])->groupBy('provider','brand')->orderBy('count','desc')->get();
            return ['revenue_by_day' => $daily, 'revenue_by_plan' => $byPlan, 'payment_methods' => $byMethod];
        }
        if ($key === 'product_performance') {
            $created = DB::table('products')->selectRaw('DATE(created_at) as date, COUNT(*) as count')->whereBetween('created_at', [$from, $to])->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();
            $status = DB::table('products')->selectRaw('status, COUNT(*) as count')->whereBetween('created_at', [$from, $to])->groupBy('status')->get();
            $visibility = DB::table('products')->selectRaw('is_public, COUNT(*) as count')->whereBetween('created_at', [$from, $to])->groupBy('is_public')->get();
            $categories = DB::table('products')->selectRaw('category as category, COUNT(*) as count')->whereBetween('created_at', [$from, $to])->groupBy('category')->orderBy('count','desc')->limit(10)->get();
            return ['created_by_day' => $created, 'status_breakdown' => $status, 'visibility' => $visibility, 'top_categories' => $categories];
        }
        if ($key === 'api_usage') {
            $byDay = DB::table('api_usage')->selectRaw('DATE(created_at) as date, COUNT(*) as count')->whereBetween('created_at', [$from, $to])->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();
            $byService = DB::table('api_usage')->selectRaw('api_service as service, COUNT(*) as count')->whereBetween('created_at', [$from, $to])->groupBy('api_service')->orderBy('count','desc')->get();
            return ['calls_by_day' => $byDay, 'calls_by_service' => $byService];
        }
        if ($key === 'platform_growth') {
            $newUsers = DB::table('users')->selectRaw('DATE(created_at) as date, COUNT(*) as count')->whereBetween('created_at', [$from, $to])->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();
            $paidConversions = DB::table('users')->selectRaw('DATE(subscription_started_at) as date, COUNT(*) as count')->where('payment_status','paid')->whereNotNull('subscription_started_at')->whereBetween('subscription_started_at', [$from, $to])->groupBy(DB::raw('DATE(subscription_started_at)'))->orderBy('date')->get();
            $churn = DB::table('users')->selectRaw('DATE(subscription_ends_at) as date, COUNT(*) as count')->where('payment_status','cancelled')->whereNotNull('subscription_ends_at')->whereBetween('subscription_ends_at', [$from, $to])->groupBy(DB::raw('DATE(subscription_ends_at)'))->orderBy('date')->get();
            return ['new_users_by_day' => $newUsers, 'paid_conversions_by_day' => $paidConversions, 'churn_by_day' => $churn];
        }
        if ($key === 'feature_adoption') {
            $startMonth = $from->copy()->startOfMonth();
            $endMonth = $to->copy()->startOfMonth();
            $rows = DB::table('usages')->select('month', DB::raw('SUM(products) as products'), DB::raw('SUM(qr_codes) as qr_codes'), DB::raw('SUM(labels) as labels'))->whereBetween('month', [$startMonth->format('Y-m'), $endMonth->format('Y-m')])->groupBy('month')->orderBy('month')->get();
            return ['usage_by_month' => $rows];
        }
        return [];
    }

    private function serialize(string $key, string $format, array $data, Carbon $from, Carbon $to): array
    {
        $base = $key . '_' . $from->toDateString() . '_' . $to->toDateString();
        if ($format === 'json') {
            return [$base . '.json', json_encode($data, JSON_PRETTY_PRINT), 'application/json'];
        }
        if ($format === 'csv') {
            $rows = [];
            $push = function($section, $row) use (&$rows) { $rows[] = array_merge(['section' => $section], (array)$row); };
            foreach ($data as $section => $items) {
                if (is_array($items)) {
                    foreach ($items as $row) { $push($section, $row); }
                } else {
                    $push($section, ['value' => $items]);
                }
            }
            $csv = $this->toCSV($rows);
            return [$base . '.csv', $csv, 'text/csv'];
        }
        // pdf fallback: store json if requested pdf but renderer isn't integrated server-side
        return [$base . '.json', json_encode($data, JSON_PRETTY_PRINT), 'application/json'];
    }

    private function toCSV(array $rows): string
    {
        if (empty($rows)) return "";
        $cols = array_values(array_unique(array_merge(...array_map(fn($r)=>array_keys((array)$r), $rows))));
        $esc = function($v){
            if ($v === null) return '';
            $s = (string)$v;
            return (str_contains($s, '"') || str_contains($s, ',') || str_contains($s, "\n")) ? '"'.str_replace('"','""',$s).'"' : $s;
        };
        $out = [];
        $out[] = implode(',', $cols);
        foreach ($rows as $r) {
            $r = (array)$r;
            $out[] = implode(',', array_map($esc, array_map(fn($c)=>$r[$c] ?? '', $cols)));
        }
        return implode("\n", $out);
    }

    private function computeNextRun(string $cadence, $now)
    {
        return match($cadence) {
            'daily' => $now->copy()->addDay(),
            'weekly' => $now->copy()->addWeek(),
            'monthly' => $now->copy()->addMonth(),
            'quarterly' => $now->copy()->addQuarter(),
            default => $now->copy()->addWeek(),
        };
    }
}

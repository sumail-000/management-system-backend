<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ReportScheduleController extends Controller
{
    public function index(Request $request)
    {
        $schedules = DB::table('report_schedules')
            ->orderBy('next_run_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'report_key' => 'required|string|in:user_activity,revenue_summary,product_performance,api_usage,platform_growth,feature_adoption',
            'cadence' => 'required|string|in:daily,weekly,monthly,quarterly',
            'format' => 'required|string|in:csv,json,pdf',
            'recipients' => 'nullable|array',
            'recipients.*' => 'email',
            'params' => 'nullable|array',
        ]);
        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $v->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $data = $v->validated();
        $now = now();
        $next = $this->computeNextRun($data['cadence'], $now);

        $id = DB::table('report_schedules')->insertGetId([
            'report_key' => $data['report_key'],
            'cadence' => $data['cadence'],
            'format' => $data['format'],
            'params' => json_encode($data['params'] ?? []),
            'recipients' => json_encode($data['recipients'] ?? []),
            'is_active' => true,
            'last_run_at' => null,
            'next_run_at' => $next,
            'admin_id' => auth('admin')->id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $schedule = DB::table('report_schedules')->find($id);
        return response()->json(['success' => true, 'data' => $schedule], 201);
    }

    public function update(Request $request, string $id)
    {
        $v = Validator::make($request->all(), [
            'cadence' => 'sometimes|string|in:daily,weekly,monthly,quarterly',
            'format' => 'sometimes|string|in:csv,json,pdf',
            'recipients' => 'sometimes|array',
            'recipients.*' => 'email',
            'params' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);
        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $v->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $data = $v->validated();
        $now = now();

        $update = [];
        foreach (['cadence','format'] as $f) if (array_key_exists($f, $data)) $update[$f] = $data[$f];
        if (array_key_exists('recipients', $data)) $update['recipients'] = json_encode($data['recipients']);
        if (array_key_exists('params', $data)) $update['params'] = json_encode($data['params']);
        if (array_key_exists('is_active', $data)) $update['is_active'] = (bool)$data['is_active'];

        if (isset($update['cadence'])) {
            $update['next_run_at'] = $this->computeNextRun($update['cadence'], $now);
        }

        if (!empty($update)) {
            $update['updated_at'] = $now;
            DB::table('report_schedules')->where('id', $id)->update($update);
        }
        $schedule = DB::table('report_schedules')->find($id);
        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $schedule]);
    }

    public function destroy(string $id)
    {
        DB::table('report_schedules')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function generated(Request $request)
    {
        $query = DB::table('generated_reports')->orderByDesc('created_at');
        if ($k = $request->query('report_key')) $query->where('report_key', $k);
        $items = $query->limit(100)->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function download(string $id)
    {
        $gr = DB::table('generated_reports')->find($id);
        if (!$gr || !$gr->file_path) return response()->json(['success' => false, 'message' => 'Not found'], 404);
        if (!Storage::disk('local')->exists($gr->file_path)) return response()->json(['success' => false, 'message' => 'File missing'], 404);
        $fullPath = storage_path('app/' . $gr->file_path);
        return response()->download($fullPath);
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

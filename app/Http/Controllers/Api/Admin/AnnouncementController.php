<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AnnouncementController extends Controller
{
    /**
     * Broadcast an announcement notification to all users.
     * POST /api/admin/announcements
     */
    public function broadcast(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string|max:100', // defaults to "announcement"
            'link' => 'nullable|string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();
        $type = $data['type'] ?? 'announcement';
        $title = $data['title'];
        $message = $data['message'];
        $link = $data['link'] ?? null;

        try {
            $start = microtime(true);
            $now = now();
            $created = 0;

            // Stream through all user IDs to avoid memory spikes on large datasets
            User::query()->select('id')->chunkById(1000, function ($users) use ($type, $title, $message, $link, $now, &$created) {
                $rows = [];
                foreach ($users as $user) {
                    $rows[] = [
                        'user_id' => $user->id,
                        'type' => $type,
                        'title' => $title,
                        'message' => $message,
                        'metadata' => json_encode(['broadcast' => true]),
                        'link' => $link,
                        'read_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if (!empty($rows)) {
                    DB::table('notifications')->insert($rows);
                    $created += count($rows);
                }
            });

            Log::channel('security')->info('Admin broadcast announcement created', [
                'admin_id' => auth('admin')->id(),
                'type' => $type,
                'title' => $title,
                'created' => $created,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Announcement sent to all users',
                'created_count' => $created,
            ]);
        } catch (\Throwable $e) {
            Log::channel('security')->error('Failed to broadcast announcement', [
                'admin_id' => auth('admin')->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send announcement',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

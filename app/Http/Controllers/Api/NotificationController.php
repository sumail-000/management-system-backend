<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\Notification;

class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $perPage = (int) $request->get('per_page', 50);
            $perPage = $perPage > 0 && $perPage <= 200 ? $perPage : 50;

            $query = Notification::where('user_id', $user->id)
                ->orderByDesc('created_at');

            if ($request->has('read')) {
                $read = filter_var($request->get('read'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($read === true) {
                    $query->whereNotNull('read_at');
                } elseif ($read === false) {
                    $query->whereNull('read_at');
                }
            }

            $notifications = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * Store a new notification for the authenticated user
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'type' => 'required|string|max:100',
                'title' => 'required|string|max:255',
                'message' => 'nullable|string',
                'metadata' => 'nullable|array',
                'link' => 'nullable|string|max:2048',
                'created_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $notification = new Notification();
            $notification->user_id = $user->id;
            $notification->type = $data['type'];
            $notification->title = $data['title'];
            $notification->message = $data['message'] ?? null;
            $notification->metadata = $data['metadata'] ?? null;
            $notification->link = $data['link'] ?? null;

            if (!empty($data['created_at'])) {
                $notification->created_at = $data['created_at'];
            }

            $notification->save();

            return response()->json([
                'success' => true,
                'data' => $notification
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notification'
            ], 500);
        }
    }

    /**
     * Mark a notification as read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $notification = Notification::where('user_id', $user->id)->findOrFail($id);
            if (is_null($notification->read_at)) {
                $notification->read_at = now();
                $notification->save();
            }

            return response()->json([
                'success' => true,
                'data' => $notification
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for the user
     */
    public function markAllRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all as read'
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $notification = Notification::where('user_id', $user->id)->findOrFail($id);
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification'
            ], 500);
        }
    }
}

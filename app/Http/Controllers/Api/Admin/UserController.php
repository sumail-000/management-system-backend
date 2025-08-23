<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'plan' => 'nullable|string|in:basic,pro,enterprise',
            'status' => 'nullable|string|in:active,inactive,suspended',
            'sort_by' => 'nullable|string|in:name,created_at,last_active',
            'sort_order' => 'nullable|string|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = User::query()
            ->with('membershipPlan')
            ->withCount('products'); // Eager load the count of products

        // Apply search
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm)
                  ->orWhere('company', 'like', $searchTerm);
            });
        }

        // Apply plan filter
        if ($request->filled('plan')) {
            $query->whereHas('membershipPlan', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->plan . '%');
            });
        }

        // Apply status filter
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'active') {
                $query->whereIn('payment_status', ['paid', 'active']);
            } elseif ($status === 'inactive') {
                $query->whereIn('payment_status', ['pending', 'failed', 'cancelled']);
            } elseif ($status === 'suspended') {
                // Add logic for suspended status if that feature is built
                // For now, it will return no users.
                $query->where('payment_status', 'suspended');
            }
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'last_active_at');
        if ($sortBy === 'last_active') {
            $sortBy = 'last_active_at';
        }
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $request->input('per_page', 10);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = User::with(['membershipPlan', 'products', 'billingHistory'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Get user statistics for the dashboard cards.
     */
    public function stats(): JsonResponse
    {
        $totalUsers = DB::table('users')->count();
        
        $activeUsers = DB::table('users')->where('is_suspended', false)->count();

        $proUsers = DB::table('users')
            ->join('membership_plans', 'users.membership_plan_id', '=', 'membership_plans.id')
            ->where('membership_plans.name', 'Pro')
            ->count();

        $enterpriseUsers = DB::table('users')
            ->join('membership_plans', 'users.membership_plan_id', '=', 'membership_plans.id')
            ->where('membership_plans.name', 'Enterprise')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'pro_users' => $proUsers,
                'enterprise_users' => $enterpriseUsers,
            ]
        ]);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user'
            ], 500);
        }
    }

    /**
     * Suspend or unsuspend the specified user.
     */
    public function suspend(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            $user->is_suspended = !$user->is_suspended;
            $user->save();

            // Notify user of suspension status change
            try {
                if ($user->is_suspended) {
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'account_suspended',
                        'title' => 'Account temporarily suspended',
                        'message' => 'Your account has been temporarily suspended due to policy concerns. If you believe this is a mistake, please open a support ticket so our team can review.',
                        'metadata' => [
                            'suspended_at' => now()->toISOString(),
                        ],
                        'link' => '/support?ref=account_suspended',
                    ]);
                } else {
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'account_reinstated',
                        'title' => 'Account reinstated',
                        'message' => 'Your account has been reinstated. Thank you for your patience.',
                        'metadata' => [
                            'reinstated_at' => now()->toISOString(),
                        ],
                        'link' => '/dashboard',
                    ]);
                }
            } catch (\Throwable $e) {}

            return response()->json([
                'success' => true,
                'message' => 'User suspension status updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user suspension status'
            ], 500);
        }
    }
}

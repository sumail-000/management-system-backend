<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminActivity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Get admin profile information
     */
    public function show(): JsonResponse
    {
        // Get the authenticated user from sanctum
        $user = Auth::guard('sanctum')->user();
        
        // Find the corresponding admin record
        $admin = null;
        if ($user instanceof \App\Models\Admin) {
            $admin = $user;
        } else {
            $admin = \App\Models\Admin::where('email', $user->email)->first();
        }
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }
        
        // Get admin's permissions
        $permissions = $admin->permissions ?? [];
        
        // Get admin's recent activity from database
        $recentActivities = $admin->getRecentActivities(5);
        $recentActivity = $recentActivities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'action' => $activity->description,
                'target' => $activity->metadata['target'] ?? '',
                'timestamp' => $activity->created_at->toISOString(),
                'type' => $activity->type
            ];
        })->toArray();
        
        return response()->json([
            'success' => true,
            'data' => [
                'profile' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'avatar' => $admin->avatar,
                    'role' => $admin->role,
                    'role_display' => $admin->role_display,
                    'is_active' => $admin->is_active,
                    'last_login_at' => $admin->last_login_at,
                    'created_at' => $admin->created_at,
                    'updated_at' => $admin->updated_at,
                    'ip_restriction_enabled' => $admin->ip_restriction_enabled,
                    'allowed_ips' => $admin->allowed_ips,
                    'two_factor_enabled' => $admin->two_factor_enabled,
                    'login_notifications_enabled' => $admin->login_notifications_enabled,
                ],
                'permissions' => $permissions,
                'recent_activity' => $recentActivity
            ]
        ]);
    }

    /**
     * Update admin profile information
     */
    public function update(Request $request): JsonResponse
    {
        // Get the authenticated user from sanctum
        $user = Auth::guard('sanctum')->user();
        
        // Find the corresponding admin record
        $admin = null;
        if ($user instanceof \App\Models\Admin) {
            $admin = $user;
        } else {
            $admin = \App\Models\Admin::where('email', $user->email)->first();
        }
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admins')->ignore($admin->id)
            ],
        ]);
        
        try {
            $originalData = $admin->only(['name', 'email']);
            $admin->update($request->only(['name', 'email']));
            
            // Log activity
            $changes = [];
            foreach (['name', 'email'] as $field) {
                if ($originalData[$field] !== $request->$field) {
                    $changes[$field] = [
                        'from' => $originalData[$field],
                        'to' => $request->$field
                    ];
                }
            }
            
            $admin->logActivity(
                AdminActivity::ACTION_PROFILE_UPDATE,
                'Profile information updated',
                AdminActivity::TYPE_PROFILE,
                [
                    'changes' => $changes,
                    'updated_fields' => array_keys($changes)
                ],
                $request->ip(),
                $request->userAgent()
            );
            
            Log::info('Admin profile updated', [
                'admin_id' => $admin->id,
                'updated_fields' => $request->only(['name', 'email'])
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $admin->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update admin profile', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update admin avatar
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        // Get the authenticated user from sanctum
        $user = Auth::guard('sanctum')->user();
        
        // Find the corresponding admin record
        $admin = null;
        if ($user instanceof \App\Models\Admin) {
            $admin = $user;
        } else {
            $admin = \App\Models\Admin::where('email', $user->email)->first();
        }
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }
        
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);
        
        try {
            // Delete old avatar if exists
            if ($admin->avatar) {
                Storage::disk('public')->delete($admin->avatar);
            }
            
            // Store new avatar
            $path = $request->file('avatar')->store('avatars/admins', 'public');
            
            // Update admin record
            $admin->update(['avatar' => $path]);
            
            // Log activity
            $admin->logActivity(
                AdminActivity::ACTION_AVATAR_UPDATE,
                'Profile avatar updated',
                AdminActivity::TYPE_PROFILE,
                [
                    'avatar_path' => $path,
                    'previous_avatar' => $admin->getOriginal('avatar')
                ],
                $request->ip(),
                $request->userAgent()
            );
            
            Log::info('Admin avatar updated', [
                'admin_id' => $admin->id,
                'avatar_path' => $path
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Avatar updated successfully',
                'data' => [
                    'avatar_path' => $path,
                    'avatar' => $path,
                    'avatar_url' => asset('storage/' . $path)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update admin avatar', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update avatar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update admin password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        // Get the authenticated user from sanctum
        $user = Auth::guard('sanctum')->user();
        
        // Find the corresponding admin record
        $admin = null;
        if ($user instanceof \App\Models\Admin) {
            $admin = $user;
        } else {
            $admin = \App\Models\Admin::where('email', $user->email)->first();
        }
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }
        
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ]);
        
        try {
            // Verify current password
            if (!Hash::check($request->current_password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }
            
            // Check if new password is the same as current
            if (Hash::check($request->new_password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password'
                ], 422);
            }
            
            // Update password
            $admin->update([
                'password' => Hash::make($request->new_password)
            ]);
            
            // Log activity
            $admin->logActivity(
                AdminActivity::ACTION_PASSWORD_CHANGE,
                'Password changed successfully',
                AdminActivity::TYPE_SECURITY,
                [
                    'changed_at' => now()->toISOString()
                ],
                $request->ip(),
                $request->userAgent()
            );
            
            Log::info('Admin password updated', [
                'admin_id' => $admin->id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update admin password', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update password: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get admin permissions
     */
    public function getPermissions(): JsonResponse
    {
        // Get the authenticated user from sanctum
        $user = Auth::guard('sanctum')->user();
        
        // Find the corresponding admin record
        $admin = null;
        if ($user instanceof \App\Models\Admin) {
            $admin = $user;
        } else {
            $admin = \App\Models\Admin::where('email', $user->email)->first();
        }
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $admin->permissions ?? [],
                'role' => $admin->role,
                'is_super_admin' => $admin->isSuperAdmin()
            ]
        ]);
    }

    /**
     * Get admin recent activity
     */
    public function getRecentActivity(): JsonResponse
    {
        // Get the authenticated user from sanctum
        $user = Auth::guard('sanctum')->user();
        
        // Find the corresponding admin record
        $admin = null;
        if ($user instanceof \App\Models\Admin) {
            $admin = $user;
        } else {
            $admin = \App\Models\Admin::where('email', $user->email)->first();
        }
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }
        
        // Get recent activities from database
        $recentActivities = $admin->getRecentActivities(10);
        $recentActivity = $recentActivities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'action' => $activity->description,
                'target' => $activity->metadata['target'] ?? '',
                'timestamp' => $activity->created_at->toISOString(),
                'type' => $activity->type
            ];
        })->toArray();
        
        return response()->json([
            'success' => true,
            'data' => $recentActivity
        ]);
    }

    /**
     * Update security settings
     */
    public function updateSecuritySettings(Request $request): JsonResponse
    {
        // Get the authenticated user from sanctum
        $user = Auth::guard('sanctum')->user();
        
        // Find the corresponding admin record
        $admin = null;
        if ($user instanceof \App\Models\Admin) {
            $admin = $user;
        } else {
            $admin = \App\Models\Admin::where('email', $user->email)->first();
        }
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }

        $request->validate([
            'setting' => 'required|string|in:ip_restriction,two_factor,login_notifications',
            'enabled' => 'required|boolean',
            'allowed_ips' => 'array|nullable',
            'allowed_ips.*' => 'ip'
        ]);

        try {
            $setting = $request->setting;
            $enabled = $request->enabled;

            switch ($setting) {
                case 'ip_restriction':
                    if ($enabled) {
                        $allowedIps = $request->allowed_ips ?? [];
                        // Add current IP if not in the list
                        $currentIp = $request->ip();
                        if (!in_array($currentIp, $allowedIps)) {
                            $allowedIps[] = $currentIp;
                        }
                        $admin->enableIpRestriction($allowedIps);
                        
                        // Log activity
                        $admin->logActivity(
                            AdminActivity::ACTION_IP_RESTRICTION_ENABLED,
                            'IP address restriction enabled',
                            AdminActivity::TYPE_SECURITY,
                            [
                                'allowed_ips' => $allowedIps,
                                'current_ip' => $currentIp
                            ],
                            $request->ip(),
                            $request->userAgent()
                        );
                    } else {
                        $admin->disableIpRestriction();
                        
                        // Log activity
                        $admin->logActivity(
                            AdminActivity::ACTION_IP_RESTRICTION_DISABLED,
                            'IP address restriction disabled',
                            AdminActivity::TYPE_SECURITY,
                            [],
                            $request->ip(),
                            $request->userAgent()
                        );
                    }
                    break;

                case 'two_factor':
                    $admin->update(['two_factor_enabled' => $enabled]);
                    break;

                case 'login_notifications':
                    $admin->update(['login_notifications_enabled' => $enabled]);
                    
                    // Log activity
                    $admin->logActivity(
                        $enabled ? AdminActivity::ACTION_LOGIN_NOTIFICATIONS_ENABLED : AdminActivity::ACTION_LOGIN_NOTIFICATIONS_DISABLED,
                        $enabled ? 'Login notifications enabled' : 'Login notifications disabled',
                        AdminActivity::TYPE_SECURITY,
                        [
                            'enabled' => $enabled
                        ],
                        $request->ip(),
                        $request->userAgent()
                    );
                    break;
            }

            Log::info('Admin security setting updated', [
                'admin_id' => $admin->id,
                'setting' => $setting,
                'enabled' => $enabled,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Security setting updated successfully',
                'data' => [
                    'ip_restriction_enabled' => $admin->fresh()->ip_restriction_enabled,
                    'allowed_ips' => $admin->fresh()->allowed_ips,
                    'two_factor_enabled' => $admin->fresh()->two_factor_enabled,
                    'login_notifications_enabled' => $admin->fresh()->login_notifications_enabled,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update admin security setting', [
                'admin_id' => $admin->id,
                'setting' => $setting,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update security setting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current IP address
     */
    public function getCurrentIp(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]
        ]);
    }
}
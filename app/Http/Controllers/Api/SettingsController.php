<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Get user settings.
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get or create user settings
            $settings = $user->settings;
            
            if (!$settings) {
                // Create default settings if they don't exist
                $settings = Setting::create([
                    'user_id' => $user->id,
                    'theme' => 'light',
                    'language' => 'english',
                    'timezone' => 'UTC',
                    'email_notifications' => true,
                    'push_notifications' => true,
                    'default_serving_unit' => 'grams',
                    'label_template_preferences' => []
                ]);
                
                Log::channel('auth')->info('Default settings created for user', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Settings retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::channel('auth')->error('Failed to retrieve user settings', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update user settings.
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Validate the request
            $validator = Validator::make($request->all(), [
                'theme' => 'sometimes|string|in:light,dark',
                'language' => 'sometimes|string|in:english,arabic',
                'timezone' => 'sometimes|string|max:50',
                'email_notifications' => 'sometimes|boolean',
                'push_notifications' => 'sometimes|boolean',
                'default_serving_unit' => 'sometimes|string|in:grams,ounces,pounds,kilograms',
                'label_template_preferences' => 'sometimes|array'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Get or create user settings
            $settings = $user->settings;
            
            if (!$settings) {
                $settings = new Setting(['user_id' => $user->id]);
            }
            
            // Update only the provided fields
            $updateData = $request->only([
                'theme',
                'language', 
                'timezone',
                'email_notifications',
                'push_notifications',
                'default_serving_unit',
                'label_template_preferences'
            ]);
            
            $settings->fill($updateData);
            $settings->save();
            
            Log::channel('auth')->info('User settings updated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'updated_fields' => array_keys($updateData)
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $settings->fresh(),
                'message' => 'Settings updated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::channel('auth')->error('Failed to update user settings', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Reset user settings to defaults.
     */
    public function reset(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get or create user settings
            $settings = $user->settings;
            
            if (!$settings) {
                $settings = new Setting(['user_id' => $user->id]);
            }
            
            // Reset to default values
            $settings->fill([
                'theme' => 'light',
                'language' => 'english',
                'timezone' => 'UTC',
                'email_notifications' => true,
                'push_notifications' => true,
                'default_serving_unit' => 'grams',
                'label_template_preferences' => []
            ]);
            
            $settings->save();
            
            Log::channel('auth')->info('User settings reset to defaults', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $settings->fresh(),
                'message' => 'Settings reset to defaults successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::channel('auth')->error('Failed to reset user settings', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available options for settings.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'themes' => ['light', 'dark'],
                'languages' => ['english', 'arabic'],
                'serving_units' => ['grams', 'ounces', 'pounds', 'kilograms'],
                'timezones' => [
                    'UTC' => 'UTC',
                    'America/New_York' => 'Eastern Time',
                    'America/Chicago' => 'Central Time',
                    'America/Denver' => 'Mountain Time',
                    'America/Los_Angeles' => 'Pacific Time',
                    'Europe/London' => 'London',
                    'Europe/Paris' => 'Paris',
                    'Asia/Dubai' => 'Dubai',
                    'Asia/Riyadh' => 'Riyadh',
                    'Asia/Tokyo' => 'Tokyo',
                    'Australia/Sydney' => 'Sydney'
                ]
            ],
            'message' => 'Settings options retrieved successfully'
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MembershipPlan;
use App\Models\PasswordResetOtp;
use App\Services\UsageTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\PasswordResetOtpMail;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Carbon\Carbon;



class AuthController extends Controller
{
    protected $usageService;
    
    public function __construct(UsageTrackingService $usageService)
    {
        $this->usageService = $usageService;
    }
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        Log::channel('auth')->info('User registration attempt', [
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'company' => 'nullable|string|max:255',
                'contact_number' => 'nullable|string|max:20',
                'tax_id' => 'nullable|string|max:50',
            ]);
        } catch (ValidationException $e) {
            Log::channel('auth')->warning('User registration validation failed', [
                'email' => $request->email,
                'errors' => $e->errors(),
                'ip_address' => $request->ip()
            ]);
            throw $e;
        }

        // Get the Basic membership plan (default for new users)
        $basicPlan = MembershipPlan::where('name', 'Basic')->first();
        
        if (!$basicPlan) {
            Log::channel('auth')->error('Basic membership plan not found during registration', [
                'email' => $request->email
            ]);
        }

        // Get selected plan or default to Basic
        $selectedPlanId = $request->membership_plan_id ?? $basicPlan?->id;
        $selectedPlan = MembershipPlan::find($selectedPlanId) ?? $basicPlan;
        
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'membership_plan_id' => $selectedPlan?->id,
                'company' => $request->company,
                'contact_number' => $request->contact_number,
                'tax_id' => $request->tax_id,
            ]);

            Log::channel('auth')->info('User created successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'membership_plan' => $selectedPlan?->name,
                'payment_status' => 'pending'
            ]);
            
            // Handle trial setup based on selected plan
            if ($selectedPlan && $selectedPlan->name === 'Basic') {
                // Basic plan users get 14-day free trial
                $user->startTrial(14);
                
                Log::channel('auth')->info('Trial started for Basic plan user', [
                    'user_id' => $user->id,
                    'trial_ends_at' => $user->trial_ends_at,
                    'trial_days' => 14
                ]);
            } else {
                // Pro/Enterprise users need payment before dashboard access
                $user->update(['payment_status' => 'pending']);
                
                Log::channel('auth')->info('Payment required for premium plan user', [
                    'user_id' => $user->id,
                    'plan' => $selectedPlan?->name,
                    'payment_status' => 'pending'
                ]);
            }

            // Laravel Sanctum's HasApiTokens trait provides createToken method
            /** @var \App\Models\User $user */
            $tokenResult = $user->createToken('auth_token');
            $token = $tokenResult->plainTextToken;
            
            // Set token expiration explicitly
            $tokenResult->accessToken->update([
                'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))
            ]);

            Log::channel('auth')->info('Authentication token generated for new user', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            // Refresh user to get updated payment status and trial info
            $user->refresh();
            
            $responseData = [
                'message' => 'User registered successfully',
                'user' => $user->load('membershipPlan'),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $tokenResult->accessToken->expires_at->toISOString(),
                'expires_in' => (int) config('sanctum.expiration', 1440) * 60, // seconds
                'payment_status' => $user->payment_status,
                'requires_payment' => !$user->canAccessDashboard(),
            ];
            
            // Add trial info for Basic plan users
            if ($user->isOnTrial()) {
                $responseData['trial_info'] = [
                    'is_trial' => true,
                    'trial_ends_at' => $user->trial_ends_at,
                    'remaining_days' => $user->getRemainingTrialDays()
                ];
            }
            
            Log::channel('auth')->info('Registration response prepared', [
                'user_id' => $user->id,
                'can_access_dashboard' => $user->canAccessDashboard(),
                'requires_payment' => !$user->canAccessDashboard()
            ]);
            
            return response()->json($responseData, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::channel('auth')->error('User registration failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            throw $e;
        }
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {


        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);
        } catch (ValidationException $e) {
            Log::channel('auth')->warning('Login validation failed', [
                'email' => $request->email,
                'errors' => $e->errors(),
                'ip_address' => $request->ip()
            ]);
            throw $e;
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            Log::channel('auth')->warning('Login failed - invalid credentials', [
                'email' => $request->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        

        
        // Laravel Sanctum's HasApiTokens trait provides createToken method
        $tokenResult = $user->createToken('auth_token');
        $token = $tokenResult->plainTextToken;
        
        // Set token expiration explicitly
        $tokenResult->accessToken->update([
            'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))
        ]);
        


        // Check if trial has expired and update status
        if ($user->isTrialExpired()) {
            $user->update(['payment_status' => 'expired']);
            Log::channel('auth')->warning('User trial expired during login', [
                'user_id' => $user->id,
                'trial_ended_at' => $user->trial_ends_at
            ]);
        }
        
        // Get user usage data
        $usage = $this->usageService->getCurrentUsage($user);
        $percentages = $this->usageService->getUsagePercentages($user);
        
        // Get subscription information
        $subscriptionInfo = $user->getSubscriptionInfo();
        
        $responseData = [
            'message' => 'Login successful',
            'user' => $user->load('membershipPlan'),
            'usage' => $usage,
            'usage_percentages' => $percentages,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $tokenResult->accessToken->expires_at->toISOString(),
            'expires_in' => (int) config('sanctum.expiration', 1440) * 60, // seconds
            'payment_status' => $user->payment_status,
            'can_access_dashboard' => $user->canAccessDashboard(),
            'requires_payment' => !$user->canAccessDashboard(),
            'subscription_info' => $subscriptionInfo,
        ];
        
        // Add trial info if user is on trial
        if ($user->isOnTrial()) {
            $responseData['trial_info'] = [
                'is_trial' => true,
                'trial_ends_at' => $user->trial_ends_at,
                'remaining_days' => $user->getRemainingTrialDays()
            ];
        }
        
        // Add subscription details if user has paid subscription
        if ($user->hasPaidSubscription()) {
            $responseData['subscription_details'] = [
                'subscription_started_at' => $user->subscription_started_at,
                'subscription_ends_at' => $user->subscription_ends_at,
                'remaining_days' => $user->getRemainingSubscriptionDays(),
                'next_renewal_date' => $user->getNextRenewalDate(),
                'stripe_customer_id' => $user->stripe_customer_id,
                'stripe_subscription_id' => $user->stripe_subscription_id,
                'auto_renew' => $user->auto_renew ?? true
            ];
        }
        

        
        return response()->json($responseData);
    }



    /**
     * Get authenticated user details
     */
    public function user(Request $request)
    {
        $user = $request->user();
        


        // Check if trial has expired and update status
        if ($user->isTrialExpired()) {
            $user->update(['payment_status' => 'expired']);
            Log::channel('auth')->warning('User trial expired during profile access', [
                'user_id' => $user->id,
                'trial_ended_at' => $user->trial_ends_at
            ]);
        }
        
        // Get user usage data
        $usage = $this->usageService->getCurrentUsage($user);
        $percentages = $this->usageService->getUsagePercentages($user);
        
        // Get subscription information
        $subscriptionInfo = $user->getSubscriptionInfo();
        
        $responseData = [
            'user' => $user->load('membershipPlan', 'settings'),
            'usage' => $usage,
            'usage_percentages' => $percentages,
            'payment_status' => $user->payment_status,
            'can_access_dashboard' => $user->canAccessDashboard(),
            'requires_payment' => !$user->canAccessDashboard(),
            'subscription_info' => $subscriptionInfo,
            'billing_information' => $user->billingInformation,
            'payment_methods' => $user->activePaymentMethods,
            'billing_history' => $user->billingHistory()->with(['membershipPlan', 'paymentMethod'])->orderBy('billing_date', 'desc')->limit(10)->get(),
        ];
        
        // Add trial info if user is on trial
        if ($user->isOnTrial()) {
            $responseData['trial_info'] = [
                'is_trial' => true,
                'trial_ends_at' => $user->trial_ends_at,
                'remaining_days' => $user->getRemainingTrialDays()
            ];
        }
        
        // Add subscription details if user has paid subscription
        if ($user->hasPaidSubscription()) {
            $responseData['subscription_details'] = [
                'subscription_started_at' => $user->subscription_started_at,
                'subscription_ends_at' => $user->subscription_ends_at,
                'remaining_days' => $user->getRemainingSubscriptionDays(),
                'next_renewal_date' => $user->getNextRenewalDate(),
                'stripe_customer_id' => $user->stripe_customer_id,
                'stripe_subscription_id' => $user->stripe_subscription_id,
                'auto_renew' => $user->auto_renew ?? true
            ];
        }
        

        

        
        return response()->json($responseData);
    }

    /**
     * Send password reset OTP
     */
    public function sendPasswordResetOtp(Request $request)
    {
        Log::channel('auth')->info('Password reset OTP requested', [
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            $request->validate(['email' => 'required|email']);
        } catch (ValidationException $e) {
            Log::channel('auth')->warning('Password reset validation failed', [
                'email' => $request->email,
                'errors' => $e->errors(),
                'ip_address' => $request->ip()
            ]);
            throw $e;
        }

        // Check if user exists
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            Log::channel('auth')->warning('Password reset OTP requested for non-existent user', [
                'email' => $request->email,
                'ip_address' => $request->ip()
            ]);
            
            throw ValidationException::withMessages([
                'email' => ['We can\'t find a user with that email address.'],
            ]);
        }

        try {
            $startTime = microtime(true);
            
            // Generate and save OTP
            $otpRecord = PasswordResetOtp::createForEmail($request->email);
            
            Log::channel('auth')->info('Password reset OTP generated', [
                'email' => $request->email,
                'otp' => $otpRecord->otp, // Remove this in production
                'expires_at' => $otpRecord->expires_at,
                'generation_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);
            
            // Send OTP via email
            try {
                $emailStartTime = microtime(true);
                Mail::to($request->email)->send(new PasswordResetOtpMail($otpRecord->otp, $request->email));
                
                Log::channel('auth')->info('Password reset OTP email sent successfully', [
                    'email' => $request->email,
                    'email_send_time_ms' => round((microtime(true) - $emailStartTime) * 1000, 2),
                    'mail_driver' => config('mail.default')
                ]);
            } catch (\Exception $emailError) {
                Log::channel('auth')->error('Failed to send OTP email', [
                    'email' => $request->email,
                    'error' => $emailError->getMessage(),
                    'mail_driver' => config('mail.default')
                ]);
                
                // Continue execution - user can still see OTP in logs for development
                Log::channel('auth')->warning('Email sending failed, but OTP is available in logs for development', [
                    'email' => $request->email
                ]);
            }
            
            Log::channel('auth')->info('Password reset OTP process completed', [
                'email' => $request->email,
                'total_process_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);
            
            return response()->json([
                'message' => 'Password reset OTP sent to your email'
            ]);
            
        } catch (\Exception $e) {
            Log::channel('auth')->error('Failed to generate/send OTP', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Failed to send reset code. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        Log::channel('auth')->info('OTP verification attempt', [
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            $request->validate([
                'email' => 'required|email',
                'otp' => 'required|string|size:6'
            ]);
        } catch (ValidationException $e) {
            Log::channel('auth')->warning('OTP verification validation failed', [
                'email' => $request->email,
                'errors' => $e->errors(),
                'ip_address' => $request->ip()
            ]);
            throw $e;
        }

        $startTime = microtime(true);
        $isValid = PasswordResetOtp::verifyOtp($request->email, $request->otp);
        $verificationTime = round((microtime(true) - $startTime) * 1000, 2);
        
        if ($isValid) {
            Log::channel('auth')->info('OTP verification successful', [
                'email' => $request->email,
                'verification_time_ms' => $verificationTime
            ]);
            
            return response()->json([
                'message' => 'OTP verified successfully',
                'verified' => true
            ]);
        }
        
        Log::channel('auth')->warning('OTP verification failed', [
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'verification_time_ms' => $verificationTime
        ]);
        
        throw ValidationException::withMessages([
            'otp' => ['Invalid or expired OTP code.'],
        ]);
    }

    /**
     * Reset password with OTP
     */
    public function resetPassword(Request $request)
    {
        Log::channel('auth')->info('Password reset attempt', [
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            $request->validate([
                'otp' => 'required|string|size:6',
                'email' => 'required|email',
                'password' => 'required|min:8|confirmed',
            ]);
        } catch (ValidationException $e) {
            Log::channel('auth')->warning('Password reset validation failed', [
                'email' => $request->email,
                'errors' => $e->errors(),
                'ip_address' => $request->ip()
            ]);
            throw $e;
        }

        // Check if there's a recently used OTP for this email (within last 5 minutes)
        $recentlyUsedOtp = PasswordResetOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('is_used', true)
            ->where('updated_at', '>', Carbon::now()->subMinutes(5))
            ->first();
        
        if (!$recentlyUsedOtp) {
            Log::channel('auth')->warning('Password reset failed - no recently verified OTP found', [
                'email' => $request->email,
                'ip_address' => $request->ip()
            ]);
            
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP code. Please verify OTP first.'],
            ]);
        }
        
        Log::channel('auth')->info('Found recently verified OTP for password reset', [
            'email' => $request->email,
            'otp_id' => $recentlyUsedOtp->id,
            'verified_at' => $recentlyUsedOtp->updated_at->toDateTimeString()
        ]);

        // Find user
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            Log::channel('auth')->warning('Password reset failed - user not found', [
                'email' => $request->email,
                'ip_address' => $request->ip()
            ]);
            
            throw ValidationException::withMessages([
                'email' => ['We can\'t find a user with that email address.'],
            ]);
        }

        try {
            Log::channel('auth')->info('Password reset processing for user', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            // Update password
            $user->forceFill([
                'password' => Hash::make($request->password)
            ])->setRememberToken(Str::random(60));

            $user->save();

            // Fire password reset event
            event(new PasswordReset($user));
            
            // Clean up used OTPs for this email
            $deletedOtpCount = PasswordResetOtp::where('email', $request->email)->count();
            PasswordResetOtp::where('email', $request->email)->delete();
            
            Log::channel('auth')->info('Cleaned up OTPs after password reset', [
                'email' => $request->email,
                'deleted_otp_count' => $deletedOtpCount
            ]);
            
            Log::channel('auth')->info('Password reset completed successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'process_completed' => true
            ]);
            
            return response()->json([
                'message' => 'Password reset successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::channel('auth')->error('Password reset processing failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Failed to reset password. Please try again.'
            ], 500);
        }
    }

    /**
     * Logout user and revoke current token
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user) {
                Log::channel('auth')->info('User logout initiated', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip_address' => $request->ip()
                ]);
                
                // Revoke current token
                $user->currentAccessToken()->delete();
                
                Log::channel('auth')->info('User logout completed successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                
                return response()->json([
                    'message' => 'Logged out successfully'
                ]);
            }
            
            return response()->json([
                'message' => 'No active session found'
            ], 401);
            
        } catch (\Exception $e) {
            Log::channel('auth')->error('Logout failed', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Logout user from all devices and revoke all tokens
     */
    public function logoutFromAllDevices(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user) {
                Log::channel('auth')->info('User logout from all devices initiated', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip_address' => $request->ip()
                ]);
                
                // Get count of tokens before deletion for logging
                $tokenCount = $user->tokens()->count();
                
                // Revoke all tokens for this user
                $user->tokens()->delete();
                
                Log::channel('auth')->info('User logout from all devices completed successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'revoked_tokens_count' => $tokenCount
                ]);
                
                return response()->json([
                    'message' => 'Logged out from all devices successfully',
                    'revoked_sessions' => $tokenCount
                ]);
            }
            
            return response()->json([
                'message' => 'No active session found'
            ], 401);
            
        } catch (\Exception $e) {
            Log::channel('auth')->error('Logout from all devices failed', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Logout from all devices failed'
            ], 500);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();
            
            Log::channel('auth')->info('Password change attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);
            
            $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|min:8|confirmed',
            ]);
            
            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                Log::channel('auth')->warning('Password change failed - incorrect current password', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip_address' => $request->ip()
                ]);
                
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }
            
            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);
            
            Log::channel('auth')->info('Password change completed successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return response()->json([
                'message' => 'Password changed successfully'
            ]);
            
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('auth')->error('Password change failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Password change failed'
            ], 500);
        }
    }

    /**
     * Delete user account
     */
    public function deleteAccount(Request $request)
    {
        try {
            $user = $request->user();
            
            Log::channel('auth')->info('Account deletion attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);
            
            $request->validate([
                'password' => 'required',
            ]);
            
            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                Log::channel('auth')->warning('Account deletion failed - incorrect password', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip_address' => $request->ip()
                ]);
                
                throw ValidationException::withMessages([
                    'password' => ['The password is incorrect.'],
                ]);
            }
            
            // Revoke all tokens
            $user->tokens()->delete();
            
            // Soft delete the user (if using soft deletes) or hard delete
            $user->delete();
            
            Log::channel('auth')->info('Account deletion completed successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return response()->json([
                'message' => 'Account deleted successfully'
            ]);
            
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('auth')->error('Account deletion failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Account deletion failed'
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            Log::channel('auth')->info('Profile update attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);
            
            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
                'company' => 'sometimes|nullable|string|max:255',
                'contact_number' => 'sometimes|nullable|string|max:20',
                'tax_id' => 'sometimes|nullable|string|max:50',
                'avatar' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);
            
            $updateData = $request->only(['name', 'email', 'company', 'contact_number', 'tax_id']);
            
            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                $avatarName = time() . '_' . $avatar->getClientOriginalName();
                $avatarPath = $avatar->storeAs('avatars', $avatarName, 'public');
                $updateData['avatar'] = $avatarPath;
                
                // Delete old avatar if exists
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }
            }
            
            $user->update($updateData);
            
            Log::channel('auth')->info('Profile update completed successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'updated_fields' => array_keys($updateData)
            ]);
            
            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user->fresh()
            ]);
            
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('auth')->error('Profile update failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Profile update failed'
            ], 500);
        }
    }
}

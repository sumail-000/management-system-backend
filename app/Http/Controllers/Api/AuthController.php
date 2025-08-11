<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Models\User;
use App\Models\Admin;
use App\Models\MembershipPlan;
use App\Models\PasswordResetOtp;
use App\Services\UsageTrackingService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\PasswordResetOtpMail;
use App\Mail\AdminLoginNotification;
use App\Models\AdminActivity;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Carbon\Carbon;



class AuthController extends Controller
{
    protected $usageService;
    protected $emailService;
    
    public function __construct(UsageTrackingService $usageService, EmailService $emailService)
    {
        $this->usageService = $usageService;
        $this->emailService = $emailService;
    }
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request)
    {
        Log::channel('auth')->info('User registration attempt', [
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Validation is handled by RegisterRequest
        $validated = $request->validated();

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
                try {
                    $user->startTrial(14);
                    Log::channel('auth')->info('Trial started for Basic plan user', [
                        'user_id' => $user->id,
                        'trial_ends_at' => $user->trial_ends_at,
                        'trial_days' => 14
                    ]);
                } catch (\Throwable $e) {
                    // If schema is missing trial columns or enum doesn't include "trial", skip trial setup gracefully
                    Log::channel('auth')->warning('Trial setup skipped due to schema mismatch', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
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
            // Token expiration is managed by Sanctum configuration (config('sanctum.expiration'))

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
                'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))->toISOString(),
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
            
            // Send welcome email
            try {
                $this->emailService->sendWelcomeEmail($user);
                Log::channel('auth')->info('Welcome email sent successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            } catch (\Exception $e) {
                Log::channel('auth')->error('Failed to send welcome email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
            }
            
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
    public function login(LoginRequest $request)
    {


        // Validation and rate limiting handled by LoginRequest
        $validated = $request->validated();

        // First try to authenticate as admin
        $admin = Admin::where('email', $request->email)->first();
        if ($admin && Hash::check($request->password, $admin->password)) {
            if (!$admin->is_active) {
                Log::channel('auth')->warning('Admin login failed - account inactive', [
                    'admin_id' => $admin->id,
                    'email' => $admin->email,
                    'ip_address' => $request->ip()
                ]);
                
                // Log activity and send login notification for failed attempt (inactive account)
                $admin->logActivity(
                    AdminActivity::ACTION_LOGIN_FAILED,
                    'Login failed - account is deactivated',
                    AdminActivity::TYPE_LOGIN,
                    ['reason' => 'Account is deactivated'],
                    $request->ip(),
                    $request->userAgent()
                );
                $this->sendAdminLoginNotification($admin, 'failed', $request, 'Account is deactivated');
                
                throw ValidationException::withMessages([
                    'email' => ['Your admin account has been deactivated.'],
                ]);
            }
            
            // Check IP restriction during login
            $clientIp = $request->ip();
            if (!$admin->isIpAllowed($clientIp)) {
                Log::channel('security')->warning('Admin login blocked due to IP restriction', [
                    'admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                    'client_ip' => $clientIp,
                    'allowed_ips' => $admin->allowed_ips,
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now()->toISOString()
                ]);
                
                // Log activity and send login notification for blocked attempt
                $admin->logActivity(
                    AdminActivity::ACTION_LOGIN_BLOCKED,
                    'Login blocked due to IP restriction',
                    AdminActivity::TYPE_SECURITY,
                    [
                        'client_ip' => $clientIp,
                        'allowed_ips' => $admin->allowed_ips,
                        'reason' => 'IP address not authorized'
                    ],
                    $request->ip(),
                    $request->userAgent()
                );
                $this->sendAdminLoginNotification($admin, 'blocked', $request, 'IP address not authorized');
                
                return response()->json([
                    'message' => 'Access denied. Your IP address is not authorized to access the admin panel.',
                    'error_code' => 'IP_RESTRICTION_VIOLATION',
                    'client_ip' => $clientIp
                ], 403);
            }
            
            // Update last login info
            $admin->updateLastLogin($request->ip());
            
            // Create token for admin
            $tokenResult = $admin->createToken('admin_auth_token');
            $token = $tokenResult->plainTextToken;
            // Token expiration is managed by Sanctum configuration (config('sanctum.expiration'))
            
            Log::channel('auth')->info('Admin login successful', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'role' => $admin->role,
                'ip_address' => $request->ip()
            ]);
            
            // Log activity and send login notification for successful attempt
            $admin->logActivity(
                AdminActivity::ACTION_LOGIN_SUCCESS,
                'Successful admin login',
                AdminActivity::TYPE_LOGIN,
                [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ],
                $request->ip(),
                $request->userAgent()
            );
            $this->sendAdminLoginNotification($admin, 'success', $request);
            
            return response()->json([
                'message' => 'Admin login successful',
                'user_type' => 'admin',
                'admin' => $admin,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))->toISOString(),
                'expires_in' => (int) config('sanctum.expiration', 1440) * 60,
                'redirect_to' => '/admin-panel',
            ]);
        }

        // Check if admin exists but password is wrong
        if ($admin) {
            Log::channel('auth')->warning('Admin login failed - invalid password', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'ip_address' => $request->ip()
            ]);
            
            // Log activity and send login notification for failed attempt (wrong password)
            $admin->logActivity(
                AdminActivity::ACTION_LOGIN_FAILED,
                'Login failed - invalid password',
                AdminActivity::TYPE_LOGIN,
                ['reason' => 'Invalid password'],
                $request->ip(),
                $request->userAgent()
            );
            $this->sendAdminLoginNotification($admin, 'failed', $request, 'Invalid password');
        }

        // If not admin, try regular user authentication
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
        
        // Check if user has a scheduled account deletion and cancel it automatically
        if ($user->deletion_scheduled_at && $user->deletion_scheduled_at->isFuture()) {
            Log::channel('auth')->info('User logged in during deletion waiting period - cancelling scheduled deletion', [
                'user_id' => $user->id,
                'email' => $user->email,
                'deletion_was_scheduled_at' => $user->deletion_scheduled_at,
                'hours_remaining' => now()->diffInHours($user->deletion_scheduled_at)
            ]);
            
            // Cancel the scheduled deletion
            $user->update([
                'deletion_scheduled_at' => null,
                'deletion_reason' => null
            ]);
            
            Log::channel('auth')->info('Scheduled account deletion cancelled automatically due to user login', [
                'user_id' => $user->id,
                'email' => $user->email,
                'cancelled_at' => now()
            ]);
        }
        
        // Laravel Sanctum's HasApiTokens trait provides createToken method
        $tokenResult = $user->createToken('auth_token');
        $token = $tokenResult->plainTextToken;
        // Token expiration is managed by Sanctum configuration (config('sanctum.expiration'))
        
        $deletionCancelled = false;
        
        // Check if trial has expired and update status
        if ($user->isTrialExpired()) {
            $user->update(['payment_status' => 'expired']);
            Log::channel('auth')->warning('User trial expired during login', [
                'user_id' => $user->id,
                'trial_ended_at' => $user->trial_ends_at
            ]);
        }
        
        // Check if deletion was cancelled during login
        if ($user->wasChanged(['deletion_scheduled_at', 'deletion_reason'])) {
            $deletionCancelled = true;
        }
        
        // Get user usage data
        $usage = $this->usageService->getCurrentUsage($user);
        $percentages = $this->usageService->getUsagePercentages($user);
        
        // Get subscription information
        $subscriptionInfo = $user->getSubscriptionInfo();
        
        $responseData = [
            'message' => 'Login successful',
            'user_type' => 'user',
            'user' => $user->load('membershipPlan'),
            'usage' => $usage,
            'usage_percentages' => $percentages,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))->toISOString(),
            'expires_in' => (int) config('sanctum.expiration', 1440) * 60, // seconds
            'payment_status' => $user->payment_status,
            'can_access_dashboard' => $user->canAccessDashboard(),
            'requires_payment' => !$user->canAccessDashboard(),
            'subscription_info' => $subscriptionInfo,
        ];
        
        // Add deletion cancellation notice if applicable
        if ($deletionCancelled) {
            $responseData['account_deletion_cancelled'] = true;
            $responseData['message'] = 'Login successful. Your scheduled account deletion has been cancelled.';
        }
        
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
        
        // Check if user has a scheduled account deletion and cancel it automatically
        if ($user->deletion_scheduled_at && $user->deletion_scheduled_at->isFuture()) {
            Log::channel('auth')->info('User accessed profile during deletion waiting period - cancelling scheduled deletion', [
                'user_id' => $user->id,
                'email' => $user->email,
                'deletion_was_scheduled_at' => $user->deletion_scheduled_at,
                'hours_remaining' => now()->diffInHours($user->deletion_scheduled_at)
            ]);
            
            // Cancel the scheduled deletion
            $user->update([
                'deletion_scheduled_at' => null,
                'deletion_reason' => null
            ]);
            
            Log::channel('auth')->info('Scheduled account deletion cancelled automatically due to user profile access', [
                'user_id' => $user->id,
                'email' => $user->email,
                'cancelled_at' => now()
            ]);
        }

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
            'deletion_info' => $user->getDeletionInfo(),
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
    public function resetPassword(PasswordResetRequest $request)
    {
        Log::channel('auth')->info('Password reset attempt', [
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Validation and rate limiting handled by PasswordResetRequest
        $validated = $request->validated();

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
                'password' => Hash::make($validated['password'])
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
    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = $request->user();
            
            Log::channel('auth')->info('Password change attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);
            
            // Validation and current password verification handled by ChangePasswordRequest
            $validated = $request->validated();
            
            // Update password
            $user->update([
                'password' => Hash::make($validated['password'])
            ]);
            
            // Send security alert for password change
            try {
                $this->emailService->sendPasswordResetSecurityAlert($user, $request->ip(), $request->userAgent());
                Log::channel('auth')->info('Password change security alert sent', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            } catch (\Exception $e) {
                Log::channel('auth')->error('Failed to send password change security alert', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
            }
            
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
     * Request account deletion (Step 1: Schedule deletion with 24-hour waiting period)
     */
    public function requestAccountDeletion(Request $request)
    {
        try {
            $user = $request->user();
            
            Log::channel('auth')->info('Account deletion request', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);
            
            $request->validate([
                'password' => 'required',
                'reason' => 'nullable|string|max:500'
            ]);
            
            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                Log::channel('auth')->warning('Account deletion request failed - incorrect password', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip_address' => $request->ip()
                ]);
                
                throw ValidationException::withMessages([
                    'password' => ['The password is incorrect.'],
                ]);
            }
            
            // Check if user already has a pending deletion
            if ($user->deletion_scheduled_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account deletion is already scheduled',
                    'deletion_scheduled_at' => $user->deletion_scheduled_at,
                    'hours_remaining' => max(0, now()->diffInHours($user->deletion_scheduled_at))
                ], 400);
            }
            
            // Schedule deletion for 24 hours from now
            $deletionDate = now()->addHours(24);
            $user->update([
                'deletion_scheduled_at' => $deletionDate,
                'deletion_reason' => $request->reason
            ]);
            
            // Send account deletion request email
            try {
                $this->emailService->sendAccountDeletionRequestEmail(
                    $user,
                    $request->reason,
                    $deletionDate->format('F j, Y \a\t g:i A T')
                );
                Log::channel('auth')->info('Account deletion request email sent', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'deletion_scheduled_at' => $deletionDate
                ]);
            } catch (\Exception $e) {
                Log::channel('auth')->error('Failed to send account deletion request email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Send security alert
            try {
                $this->emailService->sendAccountDeletionSecurityAlert($user, $request->ip(), $request->userAgent());
                Log::channel('auth')->info('Account deletion security alert sent', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            } catch (\Exception $e) {
                Log::channel('auth')->error('Failed to send account deletion security alert', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
            }
            
            Log::channel('auth')->info('Account deletion scheduled successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'deletion_scheduled_at' => $deletionDate
            ]);
            
            return response()->json([
                'message' => 'Account deletion scheduled for 24 hours from now. You will receive a final confirmation email before deletion.',
                'deletion_scheduled_at' => $deletionDate,
                'hours_remaining' => 24
            ]);
            
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('auth')->error('Account deletion request failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Account deletion request failed'
            ], 500);
        }
    }

    /**
     * Cancel account deletion request
     */
    public function cancelAccountDeletion(Request $request)
    {
        try {
            $user = $request->user();
            
            Log::channel('auth')->info('Account deletion cancellation request', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);
            
            if (!$user->deletion_scheduled_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account deletion is scheduled'
                ], 400);
            }
            
            // Cancel the scheduled deletion
            $user->update([
                'deletion_scheduled_at' => null,
                'deletion_reason' => null
            ]);
            
            Log::channel('auth')->info('Account deletion cancelled successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return response()->json([
                'message' => 'Account deletion has been cancelled successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::channel('auth')->error('Account deletion cancellation failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Account deletion cancellation failed'
            ], 500);
        }
    }

    /**
     * Get account deletion status
     */
    public function getAccountDeletionStatus(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user->deletion_scheduled_at) {
                return response()->json([
                    'scheduled' => false,
                    'message' => 'No account deletion is scheduled'
                ]);
            }
            
            $hoursRemaining = max(0, now()->diffInHours($user->deletion_scheduled_at));
            
            return response()->json([
                'scheduled' => true,
                'deletion_scheduled_at' => $user->deletion_scheduled_at,
                'hours_remaining' => $hoursRemaining,
                'reason' => $user->deletion_reason
            ]);
            
        } catch (\Exception $e) {
            Log::channel('auth')->error('Failed to get account deletion status', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to get account deletion status'
            ], 500);
        }
    }

    /**
     * Execute scheduled account deletions (called by scheduled command)
     */
    public function executeScheduledDeletions()
    {
        try {
            $usersToDelete = User::where('deletion_scheduled_at', '<=', now())
                ->whereNotNull('deletion_scheduled_at')
                ->get();
            
            foreach ($usersToDelete as $user) {
                Log::channel('auth')->info('Executing scheduled account deletion', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'scheduled_at' => $user->deletion_scheduled_at
                ]);
                
                // Send final confirmation email
                try {
                    $this->emailService->sendAccountDeletionConfirmationEmail(
                        $user,
                        now()->format('F j, Y \a\t g:i A T'),
                        false,
                        []
                    );
                } catch (\Exception $e) {
                    Log::channel('auth')->error('Failed to send final deletion confirmation email', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Revoke all tokens
                $user->tokens()->delete();
                
                // Delete the user
                $user->delete();
                
                Log::channel('auth')->info('Scheduled account deletion completed', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            }
            
            return response()->json([
                'message' => 'Scheduled deletions executed',
                'deleted_count' => $usersToDelete->count()
            ]);
            
        } catch (\Exception $e) {
            Log::channel('auth')->error('Failed to execute scheduled deletions', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to execute scheduled deletions'
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        try {
            $user = $request->user();
            
            Log::channel('auth')->info('Profile update attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);
            
            // Validation handled by UpdateProfileRequest
            $validated = $request->validated();
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

    /**
     * Send admin login notification email
     */
    private function sendAdminLoginNotification(Admin $admin, string $status, Request $request, ?string $reason = null)
    {
        // Only send if login notifications are enabled for this admin
        if (!$admin->login_notifications_enabled) {
            return;
        }

        try {
            $timestamp = now()->format('F j, Y \a\t g:i A T');
            $userAgent = $this->parseUserAgent($request->userAgent());
            $location = $this->getLocationFromIp($request->ip());

            Mail::to($admin->email)->send(new AdminLoginNotification(
                $admin,
                $status,
                $request->ip(),
                $userAgent,
                $timestamp,
                $location,
                $reason
            ));

            Log::channel('security')->info('Admin login notification email sent', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'status' => $status,
                'ip_address' => $request->ip(),
                'timestamp' => $timestamp
            ]);
        } catch (\Exception $e) {
            Log::channel('security')->error('Failed to send admin login notification email', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Parse user agent to get readable device/browser info
     */
    private function parseUserAgent(string $userAgent): string
    {
        // Simple user agent parsing - you can use a more sophisticated library if needed
        if (str_contains($userAgent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Safari')) {
            $browser = 'Safari';
        } elseif (str_contains($userAgent, 'Edge')) {
            $browser = 'Edge';
        } else {
            $browser = 'Unknown Browser';
        }

        if (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Mac')) {
            $os = 'macOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        } elseif (str_contains($userAgent, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($userAgent, 'iOS')) {
            $os = 'iOS';
        } else {
            $os = 'Unknown OS';
        }

        return "{$browser} on {$os}";
    }

    /**
     * Get location from IP address (simplified version)
     */
    private function getLocationFromIp(string $ip): string
    {
        // For localhost/development
        if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return 'Local Network';
        }

        // In production, you could integrate with a geolocation service
        // For now, return a generic message
        return 'Unknown Location';
    }
}

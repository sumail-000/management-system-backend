<?php

namespace App\Services;

use App\Mail\WelcomeEmail;
use App\Mail\SubscriptionConfirmationEmail;
use App\Mail\SubscriptionCancellationEmail;
use App\Mail\SecurityAlertEmail;
use App\Mail\UsageWarningEmail;
use App\Mail\AccountDeletionRequestEmail;
use App\Mail\AccountDeletionConfirmationEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class EmailService
{
    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(User $user): bool
    {
        try {
            Mail::to($user->email)->send(new WelcomeEmail($user));
            
            Log::info('Welcome email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send subscription confirmation email
     */
    public function sendSubscriptionConfirmationEmail(User $user, $plan): bool
    {
        try {
            Mail::to($user->email)->send(new SubscriptionConfirmationEmail($user, $plan));
            
            Log::info('Subscription confirmation email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'plan' => $plan->name ?? 'Unknown'
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send subscription confirmation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send subscription cancellation email
     */
    public function sendSubscriptionCancellationEmail(User $user, $plan, ?string $reason = null): bool
    {
        try {
            Mail::to($user->email)->send(new SubscriptionCancellationEmail($user, $plan, $reason));
            
            Log::info('Subscription cancellation email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'plan' => $plan->name ?? 'Unknown',
                'reason' => $reason
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send subscription cancellation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send security alert email
     */
    public function sendSecurityAlertEmail(
        User $user, 
        string $alertType, 
        array $details = [], 
        ?string $ipAddress = null, 
        ?string $userAgent = null
    ): bool {
        try {
            Mail::to($user->email)->send(new SecurityAlertEmail($user, $alertType, $details, $ipAddress, $userAgent));
            
            Log::info('Security alert email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'alert_type' => $alertType
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send security alert email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'alert_type' => $alertType,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send usage warning email when user hits 80% of plan limit
     */
    public function sendUsageWarningEmail(
        User $user, 
        int $usagePercentage, 
        string $usageType, 
        int $currentUsage, 
        int $limit
    ): bool {
        try {
            Mail::to($user->email)->send(new UsageWarningEmail($user, $usagePercentage, $usageType, $currentUsage, $limit));
            
            Log::info('Usage warning email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'usage_percentage' => $usagePercentage,
                'usage_type' => $usageType
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send usage warning email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send account deletion request email
     */
    public function sendAccountDeletionRequestEmail(
        User $user, 
        ?string $reason = null, 
        ?string $scheduledDate = null, 
        ?string $confirmationToken = null
    ): bool {
        try {
            Mail::to($user->email)->send(new AccountDeletionRequestEmail($user, $reason, $scheduledDate, $confirmationToken));
            
            Log::info('Account deletion request email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => $reason
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send account deletion request email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send final account deletion confirmation email
     */
    public function sendAccountDeletionConfirmationEmail(
        User $user, 
        ?string $deletionDate = null, 
        bool $dataExported = false, 
        array $exportedData = []
    ): bool {
        try {
            Mail::to($user->email)->send(new AccountDeletionConfirmationEmail($user, $deletionDate, $dataExported, $exportedData));
            
            Log::info('Account deletion confirmation email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'data_exported' => $dataExported
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send account deletion confirmation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send password reset security alert
     */
    public function sendPasswordResetSecurityAlert(User $user, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        return $this->sendSecurityAlertEmail($user, 'password_reset', [], $ipAddress, $userAgent);
    }
    
    /**
     * Send cancellation request security alert
     */
    public function sendCancellationRequestSecurityAlert(User $user, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        return $this->sendSecurityAlertEmail($user, 'cancellation_request', [], $ipAddress, $userAgent);
    }
    
    /**
     * Send account deletion request security alert
     */
    public function sendAccountDeletionSecurityAlert(User $user, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        return $this->sendSecurityAlertEmail($user, 'account_deletion_request', [], $ipAddress, $userAgent);
    }
    
    /**
     * Check and send usage warning if user hits 80% of plan limit
     */
    public function checkAndSendUsageWarning(User $user, string $usageType): bool
    {
        // Get user's plan limits
        $plan = $user->membershipPlan;
        if (!$plan) {
            return false;
        }
        
        // Define plan limits (you can adjust these based on your actual plan structure)
        $limits = [
            'Basic' => [
                'products' => 100,
                'storage' => 1024, // MB
                'orders' => 500
            ],
            'Pro' => [
                'products' => 500,
                'storage' => 5120, // MB
                'orders' => 2000
            ],
            'Premium' => [
                'products' => -1, // Unlimited
                'storage' => -1, // Unlimited
                'orders' => -1 // Unlimited
            ]
        ];
        
        $planLimits = $limits[$plan->name] ?? null;
        if (!$planLimits || !isset($planLimits[$usageType]) || $planLimits[$usageType] === -1) {
            return false; // No limit or unlimited plan
        }
        
        $limit = $planLimits[$usageType];
        
        // Get current usage (you'll need to implement these methods based on your models)
        $currentUsage = $this->getCurrentUsage($user, $usageType);
        $usagePercentage = ($currentUsage / $limit) * 100;
        
        // Send warning if usage is 80% or higher
        if ($usagePercentage >= 80) {
            return $this->sendUsageWarningEmail($user, (int)$usagePercentage, $usageType, $currentUsage, $limit);
        }
        
        return false;
    }
    
    /**
     * Get current usage for a user (implement based on your models)
     */
    private function getCurrentUsage(User $user, string $usageType): int
    {
        switch ($usageType) {
            case 'products':
                return $user->products()->count();
            case 'orders':
                return $user->orders()->count();
            case 'storage':
                // Calculate storage usage in MB
                // This is a placeholder - implement based on your file storage logic
                return 0;
            default:
                return 0;
        }
    }
}
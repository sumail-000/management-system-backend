<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;

class ProcessScheduledCancellations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process-cancellations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled subscription cancellations after waiting period';

    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing scheduled subscription cancellations...');
        
        try {
            $usersToCancel = User::where('cancellation_effective_at', '<=', now())
                ->where('cancellation_status', 'confirmed')
                ->whereNotNull('cancellation_effective_at')
                ->get();
            
            if ($usersToCancel->isEmpty()) {
                $this->info('No subscriptions scheduled for cancellation at this time.');
                return 0;
            }
            
            $this->info("Found {$usersToCancel->count()} subscriptions scheduled for cancellation.");
            
            foreach ($usersToCancel as $user) {
                $this->info("Processing cancellation for user: {$user->email} (ID: {$user->id})");
                
                Log::info('Executing scheduled subscription cancellation', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'effective_at' => $user->cancellation_effective_at,
                    'reason' => $user->cancellation_reason,
                    'stripe_subscription_id' => $user->stripe_subscription_id
                ]);
                
                // Cancel Stripe subscription immediately (the waiting period has passed)
                if ($user->stripe_subscription_id) {
                    try {
                        $this->stripeService->cancelSubscription($user->stripe_subscription_id);
                        $this->info("Stripe subscription cancelled for {$user->email}");
                        
                        Log::info('Stripe subscription cancelled successfully', [
                            'user_id' => $user->id,
                            'subscription_id' => $user->stripe_subscription_id
                        ]);
                    } catch (\Exception $e) {
                        $this->error("Failed to cancel Stripe subscription for {$user->email}: {$e->getMessage()}");
                        Log::error('Failed to cancel Stripe subscription', [
                            'user_id' => $user->id,
                            'subscription_id' => $user->stripe_subscription_id,
                            'error' => $e->getMessage()
                        ]);
                        // Continue with local cancellation even if Stripe fails
                    }
                }
                
                // Update user status to cancelled
                $user->update([
                    'payment_status' => 'cancelled',
                    'subscription_ends_at' => now(),
                    'cancellation_status' => 'completed',
                    'cancelled_at' => now()
                ]);
                
                Log::info('Scheduled subscription cancellation completed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'cancellation_reason' => $user->cancellation_reason
                ]);
                
                $this->info("✅ Subscription cancelled successfully: {$user->email}");
            }
            
            $this->info("✅ Processed {$usersToCancel->count()} scheduled subscription cancellations successfully.");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to process scheduled cancellations: {$e->getMessage()}");
            Log::error('Failed to process scheduled cancellations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }
}
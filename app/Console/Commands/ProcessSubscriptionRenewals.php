<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\MembershipPlan;
use App\Models\BillingHistory;
use App\Models\PaymentMethod;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class ProcessSubscriptionRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process-renewals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process subscription renewals for users with auto-renew enabled';

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
        $this->info('Starting subscription renewal processing...');
        
        // Get users whose subscriptions have expired and have auto-renew enabled
        $expiredUsers = User::where('subscription_ends_at', '<=', now())
            ->where('auto_renew', true)
            ->where('payment_status', '!=', 'expired')
            ->whereNotNull('stripe_customer_id')
            ->whereNotNull('stripe_subscription_id')
            ->with(['membershipPlan', 'paymentMethods'])
            ->get();

        $this->info("Found {$expiredUsers->count()} users with expired subscriptions and auto-renew enabled.");

        $successCount = 0;
        $failureCount = 0;

        foreach ($expiredUsers as $user) {
            try {
                $this->processUserRenewal($user);
                $successCount++;
                $this->info("✓ Successfully renewed subscription for user {$user->id} ({$user->email})");
            } catch (Exception $e) {
                $failureCount++;
                $this->error("✗ Failed to renew subscription for user {$user->id} ({$user->email}): {$e->getMessage()}");
                
                Log::error('Subscription renewal failed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("Subscription renewal processing completed.");
        $this->info("Successful renewals: {$successCount}");
        $this->info("Failed renewals: {$failureCount}");

        return Command::SUCCESS;
    }

    /**
     * Process renewal for a specific user
     */
    private function processUserRenewal(User $user)
    {
        DB::transaction(function () use ($user) {
            // Get user's current plan
            $currentPlan = $user->membershipPlan;
            if (!$currentPlan) {
                throw new Exception('User has no membership plan assigned');
            }

            // Get user's default payment method
            $paymentMethod = $user->paymentMethods()->where('is_default', true)->where('is_active', true)->first();
            if (!$paymentMethod) {
                throw new Exception('User has no active payment method');
            }

            // Check if payment method is expired
            $currentDate = now();
            if ($paymentMethod->expiry_year < $currentDate->year || 
                ($paymentMethod->expiry_year == $currentDate->year && $paymentMethod->expiry_month < $currentDate->month)) {
                throw new Exception('Payment method has expired');
            }

            // Attempt to charge the subscription via Stripe
            try {
                // Get the price ID for the current plan
                $priceId = $this->getPriceIdForPlan($currentPlan);
                
                // Create a new subscription or renew existing one
                $subscription = $this->stripeService->renewSubscription(
                    $user->stripe_customer_id,
                    $user->stripe_subscription_id,
                    $priceId
                );

                // Update user's subscription details
                $user->update([
                    'payment_status' => 'paid',
                    'subscription_starts_at' => now(),
                    'subscription_ends_at' => now()->addMonth(), // Assuming monthly billing
                    'stripe_subscription_id' => $subscription->id ?? $user->stripe_subscription_id,
                    'cancelled_at' => null,
                    'auto_renew' => true // Ensure auto-renew remains enabled
                ]);

                // Create billing history record
                BillingHistory::create([
                    'user_id' => $user->id,
                    'membership_plan_id' => $currentPlan->id,
                    'payment_method_id' => $paymentMethod->id,
                    'invoice_number' => BillingHistory::generateInvoiceNumber(),
                    'amount' => $currentPlan->price,
                    'currency' => 'USD',
                    'status' => 'paid',
                    'payment_date' => now(),
                    'billing_period_start' => now(),
                    'billing_period_end' => now()->addMonth(),
                    'description' => "Auto-renewal for {$currentPlan->name} plan",
                    'metadata' => json_encode([
                        'auto_renewal' => true,
                        'stripe_subscription_id' => $user->stripe_subscription_id,
                        'renewal_date' => now()->toDateString()
                    ])
                ]);

                Log::info('Subscription auto-renewed successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'plan_name' => $currentPlan->name,
                    'amount' => $currentPlan->price,
                    'next_renewal' => now()->addMonth()->toDateString()
                ]);

            } catch (Exception $stripeError) {
                // If Stripe payment fails, disable auto-renew and mark as expired
                $user->update([
                    'payment_status' => 'expired',
                    'auto_renew' => false, // Disable auto-renew on payment failure
                    'cancelled_at' => now()
                ]);

                Log::warning('Auto-renewal payment failed - subscription expired', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'stripe_error' => $stripeError->getMessage()
                ]);

                throw new Exception("Payment failed: {$stripeError->getMessage()}");
            }
        });
    }

    /**
     * Get Stripe price ID for a membership plan
     */
    private function getPriceIdForPlan(MembershipPlan $plan): string
    {
        // Map plan names to Stripe price IDs
        // In production, these should be stored in the database or config
        $priceMapping = [
            'Pro' => 'price_pro_monthly', // Replace with actual Stripe price ID
            'Enterprise' => 'price_enterprise_monthly', // Replace with actual Stripe price ID
        ];

        if (!isset($priceMapping[$plan->name])) {
            throw new Exception("No Stripe price ID configured for plan: {$plan->name}");
        }

        return $priceMapping[$plan->name];
    }
}
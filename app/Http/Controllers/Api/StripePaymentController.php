<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MembershipPlan;
use App\Models\BillingInformation;
use App\Models\PaymentMethod;
use App\Models\BillingHistory;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class StripePaymentController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Create payment intent for subscription.
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'membership_plan_id' => 'required|exists:membership_plans,id',
                'email' => 'required|email',
                'card_number' => 'required|string',
                'expiry_date' => 'required|string',
                'cvc' => 'required|string',
                'cardholder_name' => 'required|string',
                'billing_address' => 'required|array',
                'billing_address.line1' => 'required|string',
                'billing_address.city' => 'required|string',
                'billing_address.state' => 'required|string',
                'billing_address.postal_code' => 'required|string',
                'billing_address.country' => 'required|string',
            ]);
            
            /** @var User $user */
            $user = Auth::user();
            $plan = MembershipPlan::findOrFail($request->membership_plan_id);
            
            // Check if user already has this membership plan
            if ($user->membership_plan_id === $plan->id && $user->hasPaidSubscription()) {
                Log::warning('User attempted to subscribe to plan they already have', [
                    'user_id' => $user->id,
                    'current_plan_id' => $user->membership_plan_id,
                    'requested_plan_id' => $plan->id,
                    'plan_name' => $plan->name
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'You are already subscribed to the ' . $plan->name . ' plan. Your subscription is active until ' . $user->subscription_ends_at->format('M d, Y') . '.'
                ], 400);
            }
            
            Log::info('Creating Stripe payment for subscription', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => $plan->price
            ]);

            // Create or get Stripe customer
            if (!$user->stripe_customer_id) {
                $customer = $this->stripeService->createCustomer(
                    $request->email,
                    $request->cardholder_name,
                    [
                        'line1' => $request->billing_address['line1'],
                        'line2' => $request->billing_address['line2'] ?? null,
                        'city' => $request->billing_address['city'],
                        'state' => $request->billing_address['state'],
                        'postal_code' => $request->billing_address['postal_code'],
                        'country' => $request->billing_address['country'],
                    ]
                );
                $user->update(['stripe_customer_id' => $customer->id]);
                Log::info('Created Stripe customer', ['customer_id' => $customer->id]);
            }

            // Parse expiry date
            $expiryParts = explode('/', $request->expiry_date);
            $expMonth = (int) $expiryParts[0];
            $expYear = (int) ('20' . $expiryParts[1]);

            // Create payment method
            $paymentMethod = $this->stripeService->createPaymentMethod([
                'number' => str_replace(' ', '', $request->card_number),
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'cvc' => $request->cvc,
                'name' => $request->cardholder_name,
                'email' => $request->email,
                'address' => [
                    'line1' => $request->billing_address['line1'],
                    'line2' => $request->billing_address['line2'] ?? null,
                    'city' => $request->billing_address['city'],
                    'state' => $request->billing_address['state'],
                    'postal_code' => $request->billing_address['postal_code'],
                    'country' => $request->billing_address['country'],
                ]
            ]);
            Log::info('Created payment method', ['payment_method_id' => $paymentMethod->id]);

            // Attach payment method to customer
            $this->stripeService->attachPaymentMethodToCustomer(
                $paymentMethod->id,
                $user->stripe_customer_id
            );
            Log::info('Attached payment method to customer');

            // Get Stripe price ID from the plan
            $priceId = $plan->stripe_price_id;
            if (!$priceId) {
                throw new Exception('Stripe price ID not configured for plan: ' . $plan->name);
            }

            // Create subscription
            $subscription = $this->stripeService->createSubscription(
                $user->stripe_customer_id,
                $priceId,
                $paymentMethod->id
            );
            Log::info('Created subscription', ['subscription_id' => $subscription->id]);

            // Save billing information and payment method to database
            DB::transaction(function () use ($user, $request, $paymentMethod, $plan, $subscription) {
                // Save or update billing information
                BillingInformation::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'full_name' => $request->cardholder_name,
                        'email' => $request->email,
                        'company_name' => null,
                        'tax_id' => null,
                        'street_address' => $request->billing_address['line1'],
                        'city' => $request->billing_address['city'],
                        'state_province' => $request->billing_address['state'],
                        'postal_code' => $request->billing_address['postal_code'],
                        'country' => $request->billing_address['country'],
                        'phone' => null,
                    ]
                );

                // Get card brand from payment method
                $cardBrand = $paymentMethod->card->brand ?? 'unknown';
                $lastFour = $paymentMethod->card->last4 ?? substr(str_replace(' ', '', $request->card_number), -4);
                
                // Parse expiry date
                $expiryParts = explode('/', $request->expiry_date);
                $expMonth = (int) $expiryParts[0];
                $expYear = (int) ('20' . $expiryParts[1]);

                // First, deactivate all existing payment methods for this user
                PaymentMethod::where('user_id', $user->id)->update([
                    'is_default' => false,
                    'is_active' => false
                ]);

                // Save payment method (only last 4 digits and card type for security)
                // Use unique constraint on user_id and last_four to prevent duplicates
                $existingPaymentMethod = PaymentMethod::where('user_id', $user->id)
                    ->where('last_four', $lastFour)
                    ->where('brand', $cardBrand)
                    ->first();

                if ($existingPaymentMethod) {
                    // Update existing payment method
                    $existingPaymentMethod->update([
                        'provider_payment_method_id' => $paymentMethod->id,
                        'expiry_month' => $expMonth,
                        'expiry_year' => $expYear,
                        'cardholder_name' => $request->cardholder_name,
                        'is_default' => true,
                        'is_active' => true,
                        'verified_at' => now(),
                    ]);
                    $savedPaymentMethod = $existingPaymentMethod;
                } else {
                    // Create new payment method
                    $savedPaymentMethod = PaymentMethod::create([
                        'user_id' => $user->id,
                        'type' => 'card',
                        'provider' => 'stripe',
                        'provider_payment_method_id' => $paymentMethod->id,
                        'brand' => $cardBrand,
                        'last_four' => $lastFour,
                        'expiry_month' => $expMonth,
                        'expiry_year' => $expYear,
                        'cardholder_name' => $request->cardholder_name,
                        'is_default' => true,
                        'is_active' => true,
                        'verified_at' => now(),
                    ]);
                }

                // Create billing history record
                BillingHistory::create([
                    'user_id' => $user->id,
                    'membership_plan_id' => $plan->id,
                    'payment_method_id' => $savedPaymentMethod->id,
                    'invoice_number' => BillingHistory::generateInvoiceNumber(),
                    'transaction_id' => $subscription->latest_invoice->payment_intent->id ?? null,
                    'type' => 'subscription',
                    'description' => $plan->name . ' Plan - Monthly Subscription',
                    'amount' => $plan->price,
                    'currency' => 'USD',
                    'status' => 'paid',
                    'billing_date' => now(),
                    'paid_at' => now(),
                    'metadata' => [
                        'stripe_subscription_id' => $subscription->id,
                        'stripe_customer_id' => $user->stripe_customer_id,
                        'plan_name' => $plan->name
                    ]
                ]);
            });

            // Mark user as paid subscriber
            $user->markAsPaid(
                $user->stripe_customer_id,
                $subscription->id
            );
            
            // Update membership plan if different
            if ($user->membership_plan_id !== $plan->id) {
                $user->update(['membership_plan_id' => $plan->id]);
            }
            
            Log::info('Payment successful - user marked as paid and billing data saved', [
                'user_id' => $user->id,
                'plan_name' => $plan->name,
                'payment_status' => $user->payment_status,
                'subscription_id' => $subscription->id,
                'billing_saved' => true
            ]);
            
            // Get updated subscription information
            $subscriptionInfo = $user->fresh()->getSubscriptionInfo();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment successful! Welcome to ' . $plan->name . ' plan.',
                'user' => $user->fresh()->load('membershipPlan'),
                'payment_status' => $user->payment_status,
                'subscription_ends_at' => $user->subscription_ends_at,
                'can_access_dashboard' => true,
                'subscription_info' => $subscriptionInfo,
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'amount' => $plan->price
                ]
            ]);
            
        } catch (ValidationException $e) {
            Log::warning('Payment validation failed', [
                'user_id' => Auth::id(),
                'errors' => $e->errors()
            ]);
            throw $e;
            
        } catch (Exception $e) {
            Log::error('Stripe payment failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get payment status for current user.
     */
    public function getPaymentStatus(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        
        Log::info('Payment status requested', [
            'user_id' => $user->id,
            'payment_status' => $user->payment_status
        ]);
        
        // Get comprehensive subscription information
        $subscriptionInfo = $user->getSubscriptionInfo();
        
        $responseData = [
            'payment_status' => $user->payment_status,
            'can_access_dashboard' => $user->canAccessDashboard(),
            'requires_payment' => !$user->canAccessDashboard(),
            'membership_plan' => $user->membershipPlan,
            'subscription_info' => $subscriptionInfo
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
                'stripe_subscription_id' => $user->stripe_subscription_id
            ];
        }
        
        return response()->json($responseData);
    }
    
    /**
     * Request subscription cancellation (Step 1: Initial request).
     */
    public function requestCancellation(Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        /** @var User $user */
        $user = Auth::user();
        
        // Check if user has any active subscription (trial or paid)
        if (!$user->isOnTrial() && !$user->hasPaidSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription or trial found'
            ], 400);
        }

        // Check if user already has a pending or confirmed cancellation
        if ($user->hasPendingCancellation() || $user->hasConfirmedCancellation()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending cancellation request',
                'cancellation_info' => $user->getCancellationInfo()
            ], 400);
        }
        
        // Handle trial cancellation (immediate)
        if ($user->isOnTrial()) {
            $user->update([
                'payment_status' => 'expired',
                'trial_ends_at' => now()
            ]);
            
            Log::info('Trial cancelled immediately', [
                'user_id' => $user->id,
                'cancelled_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Trial cancelled successfully',
                'payment_status' => $user->payment_status
            ]);
        }
        
        // Handle paid subscription cancellation request
        if ($user->hasPaidSubscription()) {
            $user->requestCancellation($request->reason);
            
            Log::info('Subscription cancellation requested', [
                'user_id' => $user->id,
                'reason' => $request->reason,
                'requested_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Cancellation request submitted. Please confirm with your password to proceed.',
                'requires_confirmation' => true,
                'cancellation_info' => $user->getCancellationInfo()
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Unable to process cancellation request'
        ], 400);
    }

    /**
     * Confirm subscription cancellation with password (Step 2: Password verification).
     */
    public function confirmCancellation(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        /** @var User $user */
        $user = Auth::user();
        
        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password. Please try again.'
            ], 422); // Use 422 instead of 401 to avoid logout
        }

        // Check if user has a pending cancellation
        if (!$user->hasPendingCancellation()) {
            return response()->json([
                'success' => false,
                'message' => 'No pending cancellation request found'
            ], 400);
        }

        // Confirm the cancellation and set 3-day waiting period
        $user->confirmCancellation();
        
        Log::info('Subscription cancellation confirmed', [
            'user_id' => $user->id,
            'confirmed_at' => now(),
            'effective_at' => $user->cancellation_effective_at
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Cancellation confirmed. Your subscription will end in 3 days on ' . $user->cancellation_effective_at->format('M d, Y') . '. You can still cancel this request if you change your mind.',
            'cancellation_info' => $user->getCancellationInfo()
        ]);
    }

    /**
     * Cancel the cancellation request (undo cancellation).
     */
    public function cancelCancellationRequest(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        
        // Check if user has a pending or confirmed cancellation
        if (!$user->hasPendingCancellation() && !$user->hasConfirmedCancellation()) {
            return response()->json([
                'success' => false,
                'message' => 'No cancellation request found to cancel'
            ], 400);
        }

        $user->cancelCancellationRequest();
        
        Log::info('Subscription cancellation request cancelled', [
            'user_id' => $user->id,
            'cancelled_request_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Cancellation request has been cancelled. Your subscription will continue as normal.',
            'cancellation_info' => $user->getCancellationInfo()
        ]);
    }

    /**
     * Get cancellation status and information.
     */
    public function getCancellationStatus(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        
        return response()->json([
            'success' => true,
            'data' => $user->getCancellationInfo()
        ]);
    }

    /**
     * Update auto-renew setting for user subscription.
     */
    public function updateAutoRenew(Request $request): JsonResponse
    {
        $request->validate([
            'auto_renew' => 'required|boolean'
        ]);

        /** @var User $user */
        $user = Auth::user();
        $autoRenew = $request->boolean('auto_renew');

        try {
            // Update user's auto-renew setting
            $user->auto_renew = $autoRenew;
            $user->save();

            Log::info('Auto-renew setting updated', [
                'user_id' => $user->id,
                'auto_renew' => $autoRenew
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Auto-renew setting updated successfully',
                'data' => [
                    'auto_renew' => $user->auto_renew
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Auto-renew update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update auto-renew setting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed subscription information.
     */
    public function getSubscriptionDetails(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $subscriptionDetails = [
                'subscription_started_at' => $user->subscription_starts_at,
                'subscription_ends_at' => $user->subscription_ends_at,
                'remaining_days' => $user->getRemainingSubscriptionDays(),
                'next_renewal_date' => $user->getNextRenewalDate(),
                'stripe_customer_id' => $user->stripe_customer_id,
                'stripe_subscription_id' => $user->stripe_subscription_id,
                'auto_renew' => $user->auto_renew ?? true,
                'canceled_at' => $user->canceled_at,
                'cancellation_reason' => $user->cancellation_reason,
                'is_canceled' => $user->isCanceled(),
                'can_still_access' => $user->canStillAccess()
            ];

            return response()->json([
                'success' => true,
                'data' => $subscriptionDetails
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get subscription details', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription details'
            ], 500);
        }
    }

    /**
     * Update payment method for existing subscription.
     */
    public function updatePaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'card_number' => 'required|string',
            'expiry_date' => 'required|string|regex:/^[0-1][0-9]\/[0-9]{2}$/',
            'cvc' => 'required|string|min:3|max:4',
            'cardholder_name' => 'required|string|max:255'
        ]);

        /** @var User $user */
        $user = Auth::user();

        try {
            // Parse expiry date
            $expiryParts = explode('/', $request->expiry_date);
            $expMonth = (int) $expiryParts[0];
            $expYear = (int) ('20' . $expiryParts[1]);

            // Create new payment method with Stripe
            $paymentMethod = $this->stripeService->createPaymentMethod([
                'number' => str_replace(' ', '', $request->card_number),
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'cvc' => $request->cvc,
                'name' => $request->cardholder_name,
                'email' => $user->email
            ]);

            // Attach to customer
            $this->stripeService->attachPaymentMethodToCustomer(
                $paymentMethod->id,
                $user->stripe_customer_id
            );

            // Update default payment method for customer
            $this->stripeService->updateCustomerDefaultPaymentMethod(
                $user->stripe_customer_id,
                $paymentMethod->id
            );

            // Update local database
            $savedPaymentMethod = null;
            DB::transaction(function () use ($user, $paymentMethod, $request, $expMonth, $expYear, &$savedPaymentMethod) {
                $cardBrand = ucfirst($paymentMethod->card->brand);
                $lastFour = $paymentMethod->card->last4;

                // Deactivate all existing payment methods
                PaymentMethod::where('user_id', $user->id)->update([
                    'is_default' => false,
                    'is_active' => false
                ]);

                // Create or update payment method
                $savedPaymentMethod = PaymentMethod::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'last_four' => $lastFour,
                        'brand' => $cardBrand
                    ],
                    [
                        'type' => 'card',
                        'provider' => 'stripe',
                        'provider_payment_method_id' => $paymentMethod->id,
                        'expiry_month' => $expMonth,
                        'expiry_year' => $expYear,
                        'cardholder_name' => $request->cardholder_name,
                        'is_default' => true,
                        'is_active' => true,
                        'verified_at' => now()
                    ]
                );
            });

            Log::info('Payment method updated successfully', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod->id
            ]);

            // Ensure payment method was saved successfully
            if (!$savedPaymentMethod) {
                throw new Exception('Failed to save payment method to database');
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment method updated successfully',
                'data' => [
                    'id' => $savedPaymentMethod->id,
                    'brand' => $savedPaymentMethod->brand,
                    'last_four' => $savedPaymentMethod->last_four,
                    'expiry_month' => $savedPaymentMethod->expiry_month,
                    'expiry_year' => $savedPaymentMethod->expiry_year,
                    'cardholder_name' => $savedPaymentMethod->cardholder_name,
                    'is_default' => $savedPaymentMethod->is_default,
                    'type' => $savedPaymentMethod->type,
                    'provider' => $savedPaymentMethod->provider
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Payment method update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment method: ' . $e->getMessage()
            ], 500);
        }
    }
}
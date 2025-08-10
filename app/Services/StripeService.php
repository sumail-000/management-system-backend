<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Subscription;
use Stripe\Price;
use Stripe\Product;
use Stripe\PaymentIntent;
use Exception;
use Illuminate\Support\Facades\Log;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe customer
     */
    public function createCustomer($email, $name, $address = null)
    {
        try {
            return Customer::create([
                'email' => $email,
                'name' => $name,
                'address' => $address
            ]);
        } catch (Exception $e) {
            Log::error('Stripe customer creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a payment method using test tokens
     */
    public function createPaymentMethod($cardData)
    {
        try {
            // For test environment, use Stripe test payment methods
            // Map card numbers to test payment method IDs
            $testPaymentMethods = [
                '4242424242424242' => 'pm_card_visa',
                '4000056655665556' => 'pm_card_visa_debit',
                '5555555555554444' => 'pm_card_mastercard',
                '2223003122003222' => 'pm_card_mastercard',
                '4000002760003184' => 'pm_card_threeDSecure2Required',
                '4000000000000002' => 'pm_card_chargeDeclined',
            ];
            
            $cardNumber = str_replace(' ', '', $cardData['number']);
            
            // If it's a test card number, use the corresponding test payment method
            if (isset($testPaymentMethods[$cardNumber])) {
                $paymentMethodId = $testPaymentMethods[$cardNumber];
                $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
                
                // Update billing details
                $paymentMethod->billing_details = [
                    'name' => $cardData['name'],
                    'email' => $cardData['email'],
                    'address' => $cardData['address'] ?? null
                ];
                
                return $paymentMethod;
            }
            
            // For production or other cards, create normally (this should be handled with Stripe Elements)
            return PaymentMethod::create([
                'type' => 'card',
                'card' => [
                    'number' => $cardData['number'],
                    'exp_month' => $cardData['exp_month'],
                    'exp_year' => $cardData['exp_year'],
                    'cvc' => $cardData['cvc'],
                ],
                'billing_details' => [
                    'name' => $cardData['name'],
                    'email' => $cardData['email'],
                    'address' => $cardData['address'] ?? null
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Stripe payment method creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get a payment method by ID
     */
    public function getPaymentMethod($paymentMethodId)
    {
        try {
            return PaymentMethod::retrieve($paymentMethodId);
        } catch (Exception $e) {
            Log::error('Stripe payment method retrieval failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Attach payment method to customer
     */
    public function attachPaymentMethodToCustomer($paymentMethodId, $customerId)
    {
        try {
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            return $paymentMethod->attach(['customer' => $customerId]);
        } catch (Exception $e) {
            Log::error('Stripe payment method attachment failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a subscription
     */
    public function createSubscription($customerId, $priceId, $paymentMethodId)
    {
        try {
            return Subscription::create([
                'customer' => $customerId,
                'items' => [[
                    'price' => $priceId,
                ]],
                'default_payment_method' => $paymentMethodId,
                'expand' => ['latest_invoice.payment_intent'],
            ]);
        } catch (Exception $e) {
            Log::error('Stripe subscription creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a product
     */
    public function createProduct($name, $description)
    {
        try {
            return Product::create([
                'name' => $name,
                'description' => $description,
            ]);
        } catch (Exception $e) {
            Log::error('Stripe product creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a price
     */
    public function createPrice($productId, $amount, $currency = 'usd', $interval = 'month')
    {
        try {
            return Price::create([
                'product' => $productId,
                'unit_amount' => $amount * 100, // Convert to cents
                'currency' => $currency,
                'recurring' => [
                    'interval' => $interval,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Stripe price creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a payment intent for one-time payments
     */
    public function createPaymentIntent($amount, $currency = 'usd', $customerId = null, $paymentMethodId = null)
    {
        try {
            $data = [
                'amount' => $amount * 100, // Convert to cents
                'currency' => $currency,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ];

            if ($customerId) {
                $data['customer'] = $customerId;
            }

            if ($paymentMethodId) {
                $data['payment_method'] = $paymentMethodId;
                $data['confirmation_method'] = 'manual';
                $data['confirm'] = true;
            }

            return PaymentIntent::create($data);
        } catch (Exception $e) {
            Log::error('Stripe payment intent creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve a customer
     */
    public function getCustomer($customerId)
    {
        try {
            return Customer::retrieve($customerId);
        } catch (Exception $e) {
            Log::error('Stripe customer retrieval failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve a subscription
     */
    public function getSubscription($subscriptionId)
    {
        try {
            return Subscription::retrieve($subscriptionId);
        } catch (Exception $e) {
            Log::error('Stripe subscription retrieval failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel a subscription immediately
     */
    public function cancelSubscription($subscriptionId)
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);
            return $subscription->cancel();
        } catch (Exception $e) {
            Log::error('Stripe subscription cancellation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Schedule subscription cancellation at period end
     */
    public function scheduleSubscriptionCancellation($subscriptionId)
    {
        try {
            return Subscription::update($subscriptionId, [
                'cancel_at_period_end' => true,
            ]);
        } catch (Exception $e) {
            Log::error('Stripe subscription schedule cancellation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel the scheduled cancellation (reactivate subscription)
     */
    public function cancelScheduledCancellation($subscriptionId)
    {
        try {
            return Subscription::update($subscriptionId, [
                'cancel_at_period_end' => false,
            ]);
        } catch (Exception $e) {
            Log::error('Stripe cancel scheduled cancellation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a subscription
     */
    public function updateSubscription($subscriptionId, $data)
    {
        try {
            return Subscription::update($subscriptionId, $data);
        } catch (Exception $e) {
            Log::error('Stripe subscription update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Renew a subscription (for auto-renewal processing)
     */
    public function renewSubscription($customerId, $subscriptionId, $priceId)
    {
        try {
            // For test environment, we'll simulate a successful renewal
            // In production, this would handle actual Stripe subscription renewal
            $subscription = Subscription::retrieve($subscriptionId);
            
            // Update the subscription to ensure it's active
            $updatedSubscription = Subscription::update($subscriptionId, [
                'cancel_at_period_end' => false,
                'items' => [[
                    'id' => $subscription->items->data[0]->id,
                    'price' => $priceId,
                ]],
            ]);
            
            Log::info('Subscription renewed successfully', [
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId,
                'price_id' => $priceId
            ]);
            
            return $updatedSubscription;
        } catch (Exception $e) {
            Log::error('Stripe subscription renewal failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update customer's default payment method
     */
    public function updateCustomerDefaultPaymentMethod($customerId, $paymentMethodId)
    {
        try {
            return Customer::update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Stripe customer default payment method update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve a payment method from Stripe
     */
    public function retrievePaymentMethod(string $paymentMethodId)
    {
        try {
            return PaymentMethod::retrieve($paymentMethodId);
        } catch (Exception $e) {
            Log::error('Stripe payment method retrieval failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
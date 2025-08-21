<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Product;
use App\Models\Usage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'membership_plan_id',
        'payment_status',
        'trial_started_at',
        'trial_ends_at',
        'subscription_started_at',
        'subscription_ends_at',
        'stripe_customer_id',
        'stripe_subscription_id',
        'company',
        'contact_number',
        'tax_id',
        'avatar',
        'auto_renew',
        'cancelled_at',
        'cancellation_status',
        'cancellation_requested_at',
        'cancellation_effective_at',
        'cancellation_reason',
        'cancellation_confirmed',
        'deletion_scheduled_at',
        'deletion_reason',
        'last_active_at',
        'is_suspended',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'subscription_started_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'auto_renew' => 'boolean',
            'cancellation_requested_at' => 'datetime',
            'cancellation_effective_at' => 'datetime',
            'cancellation_confirmed' => 'boolean',
            'deletion_scheduled_at' => 'datetime',
            'last_active_at' => 'datetime',
            'is_suspended' => 'boolean',
        ];
    }

    /**
     * Get the avatar URL attribute.
     */
    public function getAvatarAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        return asset('storage/' . $value);
    }



    /**
     * Get the products for the user.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Product>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the user's settings.
     */
    public function settings(): HasOne
    {
        return $this->hasOne(Setting::class);
    }

    /**
     * Get the user's billing information.
     */
    public function billingInformation(): HasOne
    {
        return $this->hasOne(BillingInformation::class);
    }

    /**
     * Get the user's payment methods.
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get the user's active payment methods.
     */
    public function activePaymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class)->where('is_active', true);
    }

    /**
     * Get the user's default payment method.
     */
    public function defaultPaymentMethod(): HasOne
    {
        return $this->hasOne(PaymentMethod::class)->where('is_default', true)->where('is_active', true);
    }

    /**
     * Get the user's billing history.
     */
    public function billingHistory(): HasMany
    {
        return $this->hasMany(BillingHistory::class);
    }

    /**
     * Get the user's membership plan.
     */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * Get the user's usage records.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(Usage::class);
    }


    /**
     * Check if user has reached their product limit.
     * 
     * @return bool
     */
    public function hasReachedProductLimit(): bool
    {
        // No product limits after removing membership plans
        return false;
    }

    /**
     * Check if user is on trial period.
     */
    public function isOnTrial(): bool
    {
        return $this->payment_status === 'trial' && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if user's trial has expired.
     */
    public function isTrialExpired(): bool
    {
        return $this->payment_status === 'trial' && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isPast();
    }

    /**
     * Check if user has paid subscription.
     */
    public function hasPaidSubscription(): bool
    {
        return in_array($this->payment_status, ['paid', 'cancelled']) && 
               $this->subscription_ends_at && 
               $this->subscription_ends_at->isFuture();
    }

    /**
     * Check if user has cancelled subscription but still has access.
     */
    public function hasCancelledSubscription(): bool
    {
        return $this->payment_status === 'cancelled' && 
               $this->subscription_ends_at && 
               $this->subscription_ends_at->isFuture();
    }

    /**
     * Check if user can upgrade their plan.
     */
    public function canUpgradePlan(): bool
    {
        // User can upgrade if they have an active subscription or trial
        return $this->isOnTrial() || $this->hasPaidSubscription() || $this->hasCancelledSubscription();
    }



    /**
     * Check if subscription will auto-renew.
     */
    public function willAutoRenew(): bool
    {
        return $this->hasPaidSubscription() && 
               $this->payment_status === 'paid' && 
               ($this->auto_renew ?? true); // Default to true if not set
    }

    /**
     * Check if user can access dashboard (has paid or on valid trial).
     */
    public function canAccessDashboard(): bool
    {
        // All users can access dashboard if they have trial or paid subscription
        return $this->isOnTrial() || $this->hasPaidSubscription();
    }

    /**
     * Get remaining trial days.
     */
    public function getRemainingTrialDays(): int
    {
        if (!$this->isOnTrial()) {
            return 0;
        }
        
        return max(0, (int) now()->diffInDays($this->trial_ends_at));
    }

    /**
     * Get remaining subscription days.
     */
    public function getRemainingSubscriptionDays(): int
    {
        if (!$this->hasPaidSubscription()) {
            return 0;
        }
        
        return max(0, (int) now()->diffInDays($this->subscription_ends_at));
    }

    /**
     * Get next renewal date.
     */
    public function getNextRenewalDate(): ?string
    {
        if (!$this->hasPaidSubscription()) {
            return null;
        }
        
        return $this->subscription_ends_at?->format('Y-m-d');
    }

    /**
     * Get subscription status information.
     */
    public function getSubscriptionInfo(): array
    {
        $info = [
            'is_active' => false,
            'plan_name' => 'Standard',
            'payment_status' => $this->payment_status,
            'remaining_days' => 0,
            'next_renewal_date' => null,
            'subscription_type' => null
        ];

        if ($this->isOnTrial()) {
            $info['is_active'] = true;
            $info['subscription_type'] = 'trial';
            $info['remaining_days'] = $this->getRemainingTrialDays();
            $info['next_renewal_date'] = $this->trial_ends_at?->format('Y-m-d');
        } elseif ($this->hasPaidSubscription()) {
            $info['is_active'] = true;
            $info['subscription_type'] = 'paid';
            $info['remaining_days'] = $this->getRemainingSubscriptionDays();
            $info['next_renewal_date'] = $this->getNextRenewalDate();
        }

        return $info;
    }

    /**
     * Start trial period for user.
     */
    public function startTrial(int $days = 14): void
    {
        $this->update([
            'payment_status' => 'trial',
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays($days),
        ]);
    }

    /**
     * Mark user as paid subscriber.
     */
    public function markAsPaid(?string $stripeCustomerId = null, ?string $stripeSubscriptionId = null): void
    {
        $this->update([
            'payment_status' => 'paid',
            'subscription_started_at' => now(),
            'subscription_ends_at' => now()->addMonth(), // Monthly subscription
            'stripe_customer_id' => $stripeCustomerId,
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);
    }

    /**
     * Request subscription cancellation with 3-day waiting period.
     */
    public function requestCancellation(?string $reason = null): void
    {
        $this->update([
            'cancellation_status' => 'pending',
            'cancellation_requested_at' => now(),
            'cancellation_reason' => $reason,
            'cancellation_confirmed' => false
        ]);
    }

    /**
     * Confirm cancellation request and set effective date.
     */
    public function confirmCancellation(): void
    {
        $this->update([
            'cancellation_status' => 'confirmed',
            'cancellation_confirmed' => true,
            'cancellation_effective_at' => now()->addDays(3)
        ]);
    }

    /**
     * Cancel the cancellation request.
     */
    public function cancelCancellationRequest(): void
    {
        $this->update([
            'cancellation_status' => 'none',
            'cancellation_requested_at' => null,
            'cancellation_effective_at' => null,
            'cancellation_reason' => null,
            'cancellation_confirmed' => false
        ]);
    }

    /**
     * Check if user has a pending cancellation request.
     */
    public function hasPendingCancellation(): bool
    {
        return $this->cancellation_status === 'pending';
    }

    /**
     * Check if user has a confirmed cancellation.
     */
    public function hasConfirmedCancellation(): bool
    {
        return $this->cancellation_status === 'confirmed';
    }

    /**
     * Get days remaining until cancellation becomes effective.
     */
    public function getCancellationDaysRemaining(): int
    {
        if (!$this->hasConfirmedCancellation() || !$this->cancellation_effective_at) {
            return 0;
        }
        
        return max(0, (int) now()->diffInDays($this->cancellation_effective_at));
    }

    /**
     * Get cancellation information.
     */
    public function getCancellationInfo(): array
    {
        return [
            'status' => $this->cancellation_status,
            'requested_at' => $this->cancellation_requested_at,
            'effective_at' => $this->cancellation_effective_at,
            'reason' => $this->cancellation_reason,
            'confirmed' => $this->cancellation_confirmed,
            'days_remaining' => $this->getCancellationDaysRemaining()
        ];
    }

    /**
     * Check if user subscription is canceled.
     */
    public function isCanceled(): bool
    {
        return $this->cancellation_status === 'confirmed' || $this->payment_status === 'cancelled';
    }

    /**
     * Check if user can still access the service.
     */
    public function canStillAccess(): bool
    {
        // User can access if they have active subscription or trial
        if ($this->isOnTrial() || $this->hasPaidSubscription()) {
            return true;
        }

        // If cancellation is confirmed but not yet effective, user can still access
        if ($this->hasConfirmedCancellation() && $this->cancellation_effective_at && $this->cancellation_effective_at->isFuture()) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can generate QR codes (Premium feature).
     */
    public function canGenerateQrCodes(): bool
    {
        // QR code generation is a premium feature
        // Users need an active subscription (trial or paid)
        return $this->canAccessDashboard() && $this->membershipPlan && $this->membershipPlan->name !== 'Free';
    }

    /**
     * Increment usage for a specific feature.
     */
    public function incrementUsage(string $feature): void
    {
        // Get or create usage record for current month
        $usage = $this->usages()->firstOrCreate([
            'month' => now()->format('Y-m'),
        ], [
            'products' => 0,
            'qr_codes' => 0,
            'labels' => 0,
        ]);

        // Increment the specific feature usage
        if (in_array($feature, ['products','qr_codes', 'labels'])) {
            $usage->increment($feature);
        }
    }

    /**
     * Check if account deletion is scheduled.
     */
    public function hasDeletionScheduled(): bool
    {
        return $this->deletion_scheduled_at && $this->deletion_scheduled_at->isFuture();
    }

    /**
     * Get hours remaining until deletion.
     */
    public function getDeletionHoursRemaining(): int
    {
        if (!$this->hasDeletionScheduled()) {
            return 0;
        }
        
        return max(0, (int) now()->diffInHours($this->deletion_scheduled_at));
    }

    /**
     * Get deletion information.
     */
    public function getDeletionInfo(): array
    {
        return [
            'scheduled' => $this->hasDeletionScheduled(),
            'deletion_scheduled_at' => $this->deletion_scheduled_at,
            'hours_remaining' => $this->getDeletionHoursRemaining(),
            'reason' => $this->deletion_reason
        ];
    }
}

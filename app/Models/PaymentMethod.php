<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class PaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'provider',
        'provider_payment_method_id',
        'brand',
        'last_four',
        'expiry_month',
        'expiry_year',
        'cardholder_name',
        'is_default',
        'is_active',
        'verified_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    protected $hidden = [
        'provider_payment_method_id', // Hide sensitive external IDs
    ];

    /**
     * Get the user that owns the payment method.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the billing history for this payment method.
     */
    public function billingHistory(): HasMany
    {
        return $this->hasMany(BillingHistory::class);
    }

    /**
     * Get the masked card number for display.
     */
    protected function maskedNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => '**** **** **** ' . $this->last_four,
        );
    }

    /**
     * Get the expiry date formatted.
     */
    protected function expiryFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->expiry_month . '/' . substr($this->expiry_year, -2),
        );
    }

    /**
     * Scope to get only active payment methods.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get the default payment method.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}

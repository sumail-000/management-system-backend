<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BillingHistory extends Model
{
    protected $table = 'billing_history';
    
    protected $fillable = [
        'user_id',
        'membership_plan_id',
        'payment_method_id',
        'invoice_number',
        'transaction_id',
        'type',
        'description',
        'amount',
        'currency',
        'status',
        'billing_date',
        'due_date',
        'paid_at',
        'metadata',
        'invoice_url',
    ];

    protected $casts = [
        'billing_date' => 'datetime',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the user that owns the billing history.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the membership plan associated with this billing record.
     */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * Get the payment method used for this billing record.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $month = now()->format('m');
        $count = static::whereYear('created_at', $year)
                      ->whereMonth('created_at', now()->month)
                      ->count() + 1;
        
        return sprintf('INV-%d-%s-%03d', $year, $month, $count);
    }

    /**
     * Scope to get paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope to get pending invoices.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get invoices for a specific year.
     */
    public function scopeForYear($query, $year)
    {
        return $query->whereYear('billing_date', $year);
    }
}

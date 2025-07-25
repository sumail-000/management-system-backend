<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingInformation extends Model
{
    protected $table = 'billing_information';
    
    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'company_name',
        'tax_id',
        'street_address',
        'city',
        'state_province',
        'postal_code',
        'country',
        'phone',
    ];

    /**
     * Get the user that owns the billing information.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'price',
        'stripe_price_id',
        'description',
        'features',
        'product_limit',
        'label_limit',
        'qr_code_limit',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
    ];

    /**
     * Get the users for this membership plan.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if this plan has unlimited products.
     */
    public function hasUnlimitedProducts(): bool
    {
        return $this->product_limit === 0;
    }

    /**
     * Check if this plan has unlimited labels.
     */
    public function hasUnlimitedLabels(): bool
    {
        return $this->label_limit === 0;
    }

    /**
     * Check if this plan has unlimited QR codes.
     */
    public function hasUnlimitedQrCodes(): bool
    {
        return $this->qr_code_limit === 0;
    }
}

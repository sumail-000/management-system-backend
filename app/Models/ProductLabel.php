<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'label_type',
        'label_value',
    ];

    /**
     * Get the product that owns the label.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope for diet labels
     */
    public function scopeDiet($query)
    {
        return $query->where('label_type', 'diet');
    }

    /**
     * Scope for health labels
     */
    public function scopeHealth($query)
    {
        return $query->where('label_type', 'health');
    }

    /**
     * Scope for caution labels
     */
    public function scopeCaution($query)
    {
        return $query->where('label_type', 'caution');
    }
}
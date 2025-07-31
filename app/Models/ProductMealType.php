<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMealType extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type_category',
        'type_value',
    ];

    /**
     * Get the product that owns the meal type.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope for meal types
     */
    public function scopeMeal($query)
    {
        return $query->where('type_category', 'meal');
    }

    /**
     * Scope for dish types
     */
    public function scopeDish($query)
    {
        return $query->where('type_category', 'dish');
    }
}
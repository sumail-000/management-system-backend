<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionAutoTag extends Model
{
    use HasFactory;

    protected $table = 'nutritional_data';

    protected $fillable = [
        'product_id',
        'auto_tags',
        'analyzed_at'
    ];

    protected $casts = [
        'auto_tags' => 'array',
        'analyzed_at' => 'datetime'
    ];

    /**
     * Get the product that owns the nutrition auto tag.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
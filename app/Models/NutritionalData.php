<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionalData extends Model
{
    use HasFactory;

    protected $table = 'nutritional_data';

    protected $fillable = [
        'product_id',
        'calories',
        'total_fat',
        'saturated_fat',
        'trans_fat',
        'cholesterol',
        'sodium',
        'total_carbohydrate',
        'dietary_fiber',
        'sugars',
        'protein',
        'vitamin_a',
        'vitamin_c',
        'calcium',
        'iron',
        'potassium',
        'edamam_response',
    ];

    protected $casts = [
        'calories' => 'decimal:2',
        'total_fat' => 'decimal:2',
        'saturated_fat' => 'decimal:2',
        'trans_fat' => 'decimal:2',
        'cholesterol' => 'decimal:2',
        'sodium' => 'decimal:2',
        'total_carbohydrate' => 'decimal:2',
        'dietary_fiber' => 'decimal:2',
        'sugars' => 'decimal:2',
        'protein' => 'decimal:2',
        'vitamin_a' => 'decimal:2',
        'vitamin_c' => 'decimal:2',
        'calcium' => 'decimal:2',
        'iron' => 'decimal:2',
        'potassium' => 'decimal:2',
        'edamam_response' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
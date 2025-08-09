<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomIngredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'brand',
        'category',
        'description',
        'ingredient_list',
        'serving_size',
        'serving_unit',
        'nutrition_data',
        'vitamins_minerals',
        'additional_nutrients',
        'allergens_data',
        'nutrition_notes',
        'status',
        'is_public',
        'usage_count',
    ];

    protected $casts = [
        'serving_size' => 'decimal:2',
        'nutrition_data' => 'array',
        'vitamins_minerals' => 'array',
        'additional_nutrients' => 'array',
        'allergens_data' => 'array',
        'is_public' => 'boolean',
        'usage_count' => 'integer',
    ];

    /**
     * Get the user that owns the custom ingredient.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get active ingredients only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get ingredients for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get public ingredients
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to search ingredients by name or category
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('category', 'like', "%{$search}%")
              ->orWhere('brand', 'like', "%{$search}%");
        });
    }

    /**
     * Increment usage count when ingredient is used in a recipe
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    /**
     * Get formatted nutrition data for display
     */
    public function getFormattedNutritionAttribute()
    {
        $nutrition = $this->nutrition_data ?? [];
        $vitamins = $this->vitamins_minerals ?? [];
        $additional = $this->additional_nutrients ?? [];

        return array_merge($nutrition, $vitamins, $additional);
    }

    /**
     * Get allergens as a simple array
     */
    public function getAllergensListAttribute()
    {
        $allergens = $this->allergens_data ?? [];
        return $allergens['contains'] ?? [];
    }
}
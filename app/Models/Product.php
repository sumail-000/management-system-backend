<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'category_id',
        'serving_size',
        'serving_unit',
        'servings_per_container',
        'is_public',
        'is_pinned',
        'is_favorite',
        'status',
        'image_url',
        'image_path',
        'ingredient_notes',
        'ingredients_data',
        // Recipe fields
        'recipe_uri',
        'recipe_source',
        'source_url',
        'prep_time',
        'cook_time',
        'total_time',
        'skill_level',
        'time_category',
        'cuisine_type',
        'difficulty',
        'total_co2_emissions',
        'co2_emissions_class',
        'recipe_yield',
        'total_weight',
        'weight_per_serving',
        'total_recipe_calories',
        'calories_per_serving_recipe',
        // Recipe metadata
        'diet_labels',
        'health_labels',
        'caution_labels',
        'meal_types',
        'dish_types',
        'recipe_tags',
        // Recipe rating and nutrition score
        'datametrics_rating',
        'nutrition_score',
        // Individual macronutrient fields per serving
        'protein_per_serving',
        'carbs_per_serving',
        'fat_per_serving',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_pinned' => 'boolean',
        'is_favorite' => 'boolean',
        'serving_size' => 'decimal:2',
        'servings_per_container' => 'integer',
        'ingredients_data' => 'array',
        // Recipe metadata casts
        'diet_labels' => 'array',
        'health_labels' => 'array',
        'caution_labels' => 'array',
        'meal_types' => 'array',
        'dish_types' => 'array',
        'recipe_tags' => 'array',
    ];

    protected $appends = [
        'image',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get ingredients data from JSON column
     */
    public function getIngredientsAttribute(): array
    {
        return $this->ingredients_data ?? [];
    }

    /**
     * Set ingredients data to JSON column
     */
    public function setIngredientsAttribute(array $ingredients): void
    {
        $this->ingredients_data = $ingredients;
    }

    /**
     * Get nutrition data from the first ingredient that has it
     */
    public function getNutritionalDataAttribute(): ?array
    {
        $ingredients = $this->ingredients_data ?? [];
        
        foreach ($ingredients as $ingredient) {
            if (isset($ingredient['nutrition_data']) && !empty($ingredient['nutrition_data'])) {
                return $ingredient['nutrition_data'];
            }
        }
        
        return null;
    }

    // Removed old relationships - ingredients now stored as JSON in ingredients_data column

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    /**
     * Get the collections that contain this product.
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)
            ->withTimestamps();
    }



    /**
     * Get the full image URL for the product
     */
    public function getImageUrlAttribute(): ?string
    {
        if (isset($this->attributes['image_url']) && $this->attributes['image_url']) {
            return $this->attributes['image_url'];
        }
        
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }
        
        return null;
    }

    /**
     * Get the image URL (accessor for API responses)
     */
    public function getImageAttribute(): ?string
    {
        return $this->getImageUrlAttribute();
    }
}

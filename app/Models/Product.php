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
        'is_public',
        'is_pinned',
        'is_favorite',
        'status',
        'creation_step',
        'image_url',
        'image_path',
        'ingredient_notes',
        'ingredients_data',
        'nutrition_data',
        'serving_configuration',
        'ingredient_statements',
        'allergens_data',
        'total_weight',
        'servings_per_container',
        'serving_size_grams',
        'ingredients_updated_at',
        'nutrition_updated_at',
        'serving_updated_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_pinned' => 'boolean',
        'is_favorite' => 'boolean',
        'ingredients_data' => 'array',
        'nutrition_data' => 'array',
        'serving_configuration' => 'array',
        'ingredient_statements' => 'array',
        'allergens_data' => 'array',
        'total_weight' => 'decimal:2',
        'serving_size_grams' => 'decimal:2',
        'ingredients_updated_at' => 'datetime',
        'nutrition_updated_at' => 'datetime',
        'serving_updated_at' => 'datetime',
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
     * Set nutrition data to JSON column
     */
    public function setNutritionDataAttribute(?array $nutritionData): void
    {
        $this->attributes['nutrition_data'] = $nutritionData ? json_encode($nutritionData) : null;
    }

    /**
     * Get nutrition data from JSON column
     */
    public function getNutritionDataAttribute(): ?array
    {
        return $this->attributes['nutrition_data'] ? json_decode($this->attributes['nutrition_data'], true) : null;
    }

    /**
     * Set serving configuration to JSON column
     */
    public function setServingConfigurationAttribute(?array $servingConfig): void
    {
        $this->attributes['serving_configuration'] = $servingConfig ? json_encode($servingConfig) : null;
    }

    /**
     * Get serving configuration from JSON column
     */
    public function getServingConfigurationAttribute(): ?array
    {
        return $this->attributes['serving_configuration'] ? json_decode($this->attributes['serving_configuration'], true) : null;
    }

    /**
     * Check if recipe creation is complete
     */
    public function isCreationComplete(): bool
    {
        return $this->creation_step === 'completed';
    }

    /**
     * Update creation step and timestamp
     */
    public function updateCreationStep(string $step): void
    {
        $this->creation_step = $step;
        
        // Update relevant timestamp
        switch ($step) {
            case 'ingredients_added':
                $this->ingredients_updated_at = now();
                break;
            case 'nutrition_analyzed':
                $this->nutrition_updated_at = now();
                break;
            case 'serving_configured':
                $this->serving_updated_at = now();
                break;
        }
        
        $this->save();
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
     * Get the image URL (accessor for API responses)
     */
    public function getImageAttribute(): ?string
    {
        // Check if we have a direct image URL
        if (isset($this->attributes['image_url']) && $this->attributes['image_url']) {
            return $this->attributes['image_url'];
        }
        
        // Check if we have a local image path
        if (isset($this->attributes['image_path']) && $this->attributes['image_path']) {
            return asset('storage/' . $this->attributes['image_path']);
        }
        
        return null;
    }
}

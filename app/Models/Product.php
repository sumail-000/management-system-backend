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
        'tags',
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
        // Recipe fields
        'recipe_uri',
        'recipe_source',
        'recipe_url',
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
    ];

    protected $casts = [
        'tags' => 'array',
        'is_public' => 'boolean',
        'is_pinned' => 'boolean',
        'is_favorite' => 'boolean',
        'serving_size' => 'decimal:2',
        'servings_per_container' => 'integer',
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

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredient')
                    ->withPivot(['amount', 'unit', 'order'])
                    ->orderBy('order');
    }

    public function nutritionAutoTags(): HasMany
    {
        return $this->hasMany(NutritionAutoTag::class);
    }

    public function nutritionalData(): HasMany
    {
        return $this->hasMany(NutritionalData::class);
    }

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
     * Get the product labels (diet, health, caution).
     */
    public function productLabels(): HasMany
    {
        return $this->hasMany(ProductLabel::class);
    }

    /**
     * Get the product meal types.
     */
    public function mealTypes(): HasMany
    {
        return $this->hasMany(ProductMealType::class);
    }

    /**
     * Get the product recipe tags.
     */
    public function recipeTags(): HasMany
    {
        return $this->hasMany(ProductRecipeTag::class);
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

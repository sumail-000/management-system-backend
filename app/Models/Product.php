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
        'image_url',
        'image_path',
        'ingredient_notes',
        'ingredients_data',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_pinned' => 'boolean',
        'is_favorite' => 'boolean',
        'ingredients_data' => 'array',
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

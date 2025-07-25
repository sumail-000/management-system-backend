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
        'status',
        'image_url',
        'image_path',
        'ingredient_notes',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_public' => 'boolean',
        'is_pinned' => 'boolean',
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

    public function nutritionalData(): HasMany
    {
        return $this->hasMany(NutritionalData::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecipeTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'tag_value',
        'tag_source',
    ];

    /**
     * Get the product that owns the recipe tag.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope for recipe tags
     */
    public function scopeRecipe($query)
    {
        return $query->where('tag_source', 'recipe');
    }

    /**
     * Scope for user tags
     */
    public function scopeUser($query)
    {
        return $query->where('tag_source', 'user');
    }

    /**
     * Scope for auto-generated tags
     */
    public function scopeAuto($query)
    {
        return $query->where('tag_source', 'auto');
    }
}
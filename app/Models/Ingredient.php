<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'edamam_id',
        'allergens',
        'tags',
        'notes',
    ];

    protected $casts = [
        'allergens' => 'array',
        'tags' => 'array',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_ingredient')
                    ->withPivot(['amount', 'unit', 'order'])
                    ->orderBy('order');
    }
}

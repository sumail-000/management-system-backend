<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use App\Models\User;

/**
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property array $basic_nutrition
 * @property array $macronutrients
 * @property array $micronutrients
 * @property array $daily_values
 * @property array|null $health_labels
 * @property array|null $diet_labels
 * @property array|null $allergens
 * @property array|null $warnings
 * @property array|null $high_nutrients
 * @property array|null $nutrition_summary
 * @property array $analysis_metadata
 * @property float|null $total_calories
 * @property int|null $servings
 * @property float|null $weight_per_serving
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class NutritionalData extends Model
{
    use HasFactory;

    protected $table = 'nutritional_data';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'product_id',
        'basic_nutrition',
        'macronutrients',
        'micronutrients',
        'daily_values',
        'health_labels',
        'diet_labels',
        'allergens',
        'warnings',
        'high_nutrients',
        'nutrition_summary',
        'analysis_metadata',
        'total_calories',
        'servings',
        'weight_per_serving',
    ];

    protected $casts = [
        'basic_nutrition' => 'array',
        'macronutrients' => 'array',
        'micronutrients' => 'array',
        'daily_values' => 'array',
        'health_labels' => 'array',
        'diet_labels' => 'array',
        'allergens' => 'array',
        'warnings' => 'array',
        'high_nutrients' => 'array',
        'nutrition_summary' => 'array',
        'analysis_metadata' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convert database record to frontend structure
     */
    public function toFrontendStructure()
    {
        return [
            'id' => $this->getId(),
            'product_id' => $this->product_id,
            'basic_nutrition' => $this->basic_nutrition,
            'macronutrients' => $this->macronutrients,
            'micronutrients' => $this->micronutrients,
            'daily_values' => $this->daily_values,
            'health_labels' => $this->health_labels,
            'diet_labels' => $this->diet_labels,
            'allergens' => $this->allergens,
            'warnings' => $this->warnings,
            'high_nutrients' => $this->high_nutrients,
            'nutrition_summary' => $this->nutrition_summary,
            'analysis_metadata' => $this->analysis_metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the primary key for the model.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->getKey();
    }

    /**
     * Create nutrition data from frontend request
     */
    public static function createFromFrontendData($data)
    {
        // Extract quick access fields from basic_nutrition
        $totalCalories = $data['basic_nutrition']['total_calories'] ?? null;
        $servings = $data['basic_nutrition']['servings'] ?? null;
        $weightPerServing = $data['basic_nutrition']['weight_per_serving'] ?? null;

        return self::create([
            'user_id' => auth()->id(),
            'product_id' => $data['product_id'],
            'basic_nutrition' => $data['basic_nutrition'],
            'macronutrients' => $data['macronutrients'],
            'micronutrients' => $data['micronutrients'],
            'daily_values' => $data['daily_values'],
            'health_labels' => $data['health_labels'] ?? null,
            'diet_labels' => $data['diet_labels'] ?? null,
            'allergens' => $data['allergens'] ?? null,
            'warnings' => $data['warnings'] ?? null,
            'high_nutrients' => $data['high_nutrients'] ?? null,
            'nutrition_summary' => $data['nutrition_summary'] ?? null,
            'analysis_metadata' => $data['analysis_metadata'],
            'total_calories' => $totalCalories,
            'servings' => $servings,
            'weight_per_serving' => $weightPerServing,
        ]);
    }

}
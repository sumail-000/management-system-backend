<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add the new ingredients_data JSON column to products table
        Schema::table('products', function (Blueprint $table) {
            $table->json('ingredients_data')->nullable()->after('ingredient_notes');
        });

        // Migrate existing data from product_ingredient table to the new JSON column
        $this->migrateExistingData();

        // Drop the old product_ingredient table and ingredients table
        Schema::dropIfExists('product_ingredient');
        Schema::dropIfExists('ingredients');
    }

    /**
     * Migrate existing data from product_ingredient table to JSON format
     */
    private function migrateExistingData(): void
    {
        // Get all products with their ingredients
        $products = DB::table('products')
            ->select('id')
            ->get();

        foreach ($products as $product) {
            // Get all ingredients for this product with enhanced data
            $ingredients = DB::table('product_ingredient')
                ->leftJoin('ingredients', 'product_ingredient.ingredient_id', '=', 'ingredients.id')
                ->where('product_ingredient.product_id', $product->id)
                ->select(
                    'ingredients.name',
                    'ingredients.description',
                    'ingredients.edamam_food_id',
                    'ingredients.allergens',
                    'ingredients.tags',
                    'ingredients.notes',
                    'product_ingredient.order',
                    'product_ingredient.text',
                    'product_ingredient.quantity',
                    'product_ingredient.measure',
                    'product_ingredient.weight',
                    'product_ingredient.food_category',
                    'product_ingredient.food_id',
                    'product_ingredient.image_url',
                    'product_ingredient.is_main_ingredient',
                    'product_ingredient.nutrition_data'
                )
                ->orderBy('product_ingredient.order')
                ->get();

            if ($ingredients->isNotEmpty()) {
                // Convert to array and handle JSON fields
                $ingredientsArray = $ingredients->map(function ($ingredient) {
                    return [
                        'name' => $ingredient->name,
                        'description' => $ingredient->description,
                        'edamam_food_id' => $ingredient->edamam_food_id,
                        'allergens' => $ingredient->allergens ? json_decode($ingredient->allergens, true) : null,
                        'tags' => $ingredient->tags ? json_decode($ingredient->tags, true) : null,
                        'notes' => $ingredient->notes,
                        'order' => $ingredient->order,
                        'text' => $ingredient->text,
                        'quantity' => $ingredient->quantity,
                        'measure' => $ingredient->measure,
                        'weight' => $ingredient->weight,
                        'food_category' => $ingredient->food_category,
                        'food_id' => $ingredient->food_id,
                        'image_url' => $ingredient->image_url,
                        'is_main_ingredient' => (bool) $ingredient->is_main_ingredient,
                        'nutrition_data' => $ingredient->nutrition_data ? json_decode($ingredient->nutrition_data, true) : null,
                    ];
                })->toArray();

                // Update the product with the JSON data
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['ingredients_data' => json_encode($ingredientsArray)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the ingredients table
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('edamam_food_id')->nullable();
            $table->json('allergens')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Recreate the product_ingredient table
        Schema::create('product_ingredient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->string('text')->nullable();
            $table->decimal('quantity', 10, 3)->nullable();
            $table->string('measure')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('food_category')->nullable();
            $table->string('food_id')->nullable();
            $table->text('image_url')->nullable();
            $table->boolean('is_main_ingredient')->default(false);
            $table->json('nutrition_data')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'ingredient_id']);
        });

        // Migrate data back from JSON to relational format
        $this->migrateDataBack();

        // Remove the JSON column from products table
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('ingredients_data');
        });
    }

    /**
     * Migrate data back from JSON to relational format (for rollback)
     */
    private function migrateDataBack(): void
    {
        $products = DB::table('products')
            ->whereNotNull('ingredients_data')
            ->select('id', 'ingredients_data')
            ->get();

        foreach ($products as $product) {
            $ingredientsData = json_decode($product->ingredients_data, true);
            
            if (is_array($ingredientsData)) {
                foreach ($ingredientsData as $ingredientData) {
                    // Create ingredient if it doesn't exist
                    $ingredientId = DB::table('ingredients')->insertGetId([
                        'name' => $ingredientData['name'] ?? 'Unknown',
                        'description' => $ingredientData['description'] ?? null,
                        'edamam_food_id' => $ingredientData['edamam_food_id'] ?? null,
                        'allergens' => isset($ingredientData['allergens']) ? json_encode($ingredientData['allergens']) : null,
                        'tags' => isset($ingredientData['tags']) ? json_encode($ingredientData['tags']) : null,
                        'notes' => $ingredientData['notes'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Create product_ingredient relationship
                    DB::table('product_ingredient')->insert([
                        'product_id' => $product->id,
                        'ingredient_id' => $ingredientId,
                        'order' => $ingredientData['order'] ?? 0,
                        'text' => $ingredientData['text'] ?? null,
                        'quantity' => $ingredientData['quantity'] ?? null,
                        'measure' => $ingredientData['measure'] ?? null,
                        'weight' => $ingredientData['weight'] ?? null,
                        'food_category' => $ingredientData['food_category'] ?? null,
                        'food_id' => $ingredientData['food_id'] ?? null,
                        'image_url' => $ingredientData['image_url'] ?? null,
                        'is_main_ingredient' => $ingredientData['is_main_ingredient'] ?? false,
                        'nutrition_data' => isset($ingredientData['nutrition_data']) ? json_encode($ingredientData['nutrition_data']) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};
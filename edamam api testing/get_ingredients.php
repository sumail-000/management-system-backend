<?php
/**
 * Standalone PHP Terminal Script to Get Ingredients from Recipe Name
 * Uses Edamam Recipe Search API
 * 
 * Usage: php get_ingredients.php "Recipe Name"
 * Example: php get_ingredients.php "Chicken Biryani"
 */

// Edamam Recipe Search API Credentials
define('RECIPE_APP_ID', '5ab9b74d');
define('RECIPE_APP_KEY', '0d76053275625acbab556cc56ed691f6');
define('RECIPE_API_URL', 'https://api.edamam.com/api/recipes/v2');

/**
 * Get ingredients from recipe name
 * @param string $recipeName The name of the recipe to search for
 * @return array Result array with success status and data
 */
function getIngredientsFromRecipeName($recipeName) {
    $url = RECIPE_API_URL;
    
    $params = [
        'type' => 'public',
        'q' => $recipeName,
        'app_id' => RECIPE_APP_ID,
        'app_key' => RECIPE_APP_KEY,
        'random' => 'false',
        'field' => ['label', 'image', 'url', 'dietLabels', 'ingredientLines']
    ];
    
    $headers = [
        'Edamam-Account-User: terminal-user'
    ];
    
    // Build query string
    $queryString = http_build_query($params);
    $fullUrl = $url . '?' . $queryString;
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $error
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => 'HTTP Error: ' . $httpCode . ' - ' . $response
        ];
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['hits']) || empty($data['hits'])) {
        return [
            'success' => false,
            'error' => 'No recipes found for: ' . $recipeName
        ];
    }
    
    // Get the first recipe match
    $firstRecipe = $data['hits'][0]['recipe'];
    
    return [
        'success' => true,
        'data' => [
            'recipe_name' => $firstRecipe['label'] ?? 'Unknown Recipe',
            'ingredients' => $firstRecipe['ingredientLines'] ?? [],
            'image' => $firstRecipe['image'] ?? '',
            'url' => $firstRecipe['url'] ?? '',
            'diet_labels' => $firstRecipe['dietLabels'] ?? []
        ]
    ];
}

/**
 * Display ingredients only in a clean format
 * @param array $recipeData The recipe data array
 */
function displayIngredients($recipeData) {
    echo "\n📋 INGREDIENTS:\n";
    echo str_repeat('-', 30) . "\n";
    
    foreach ($recipeData['ingredients'] as $index => $ingredient) {
        echo sprintf("%2d. %s\n", $index + 1, $ingredient);
    }
    
    echo str_repeat('-', 30) . "\n";
    echo "Total: " . count($recipeData['ingredients']) . " ingredients\n\n";
}

/**
 * Main execution
 */
function main() {
    global $argv;
    
    // Check if recipe name is provided
    if (!isset($argv[1]) || empty(trim($argv[1]))) {
        echo "\n❌ Error: Please provide a recipe name\n";
        echo "Usage: php get_ingredients.php \"Recipe Name\"\n";
        echo "Example: php get_ingredients.php \"Chicken Biryani\"\n\n";
        exit(1);
    }
    
    $recipeName = trim($argv[1]);
    
    echo "\n🔍 Searching for ingredients in: \"$recipeName\"...\n";
    
    $result = getIngredientsFromRecipeName($recipeName);
    
    if ($result['success']) {
        displayIngredients($result['data']);
        
        // Ask if user wants to save to file
        echo "💾 Save ingredients to file? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($input) === 'y' || strtolower($input) === 'yes') {
            $filename = 'ingredients_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $recipeName) . '.txt';
            $content = "Ingredients for: " . $recipeName . "\n";
            $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
            foreach ($result['data']['ingredients'] as $index => $ingredient) {
                $content .= ($index + 1) . ". $ingredient\n";
            }
            
            file_put_contents($filename, $content);
            echo "✅ Ingredients saved to: $filename\n\n";
        }
        
    } else {
        echo "\n❌ Error: " . $result['error'] . "\n\n";
        exit(1);
    }
}

// Run the script
if (php_sapi_name() === 'cli') {
    main();
} else {
    echo "This script must be run from the command line.\n";
    exit(1);
}

?>
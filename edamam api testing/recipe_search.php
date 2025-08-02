<?php
/**
 * Standalone PHP Terminal Script for Recipe Search
 * Uses Edamam Recipe Search API
 * 
 * Usage: php recipe_search.php "Search Query"
 * Example: php recipe_search.php "chicken curry"
 */

// Edamam Recipe Search API Credentials
define('RECIPE_APP_ID', '5ab9b74d');
define('RECIPE_APP_KEY', '0d76053275625acbab556cc56ed691f6');
define('RECIPE_API_URL', 'https://api.edamam.com/api/recipes/v2');

/**
 * Search for recipes
 * @param string $query The search query
 * @param int $limit Number of results to return (default: 10)
 * @return array Result array with success status and data
 */
function searchRecipes($query, $limit = 10) {
    $url = RECIPE_API_URL;
    
    $params = [
        'type' => 'public',
        'q' => $query,
        'app_id' => RECIPE_APP_ID,
        'app_key' => RECIPE_APP_KEY,
        'random' => 'false',
        'from' => 0,
        'to' => $limit,
        'field' => ['label', 'image', 'url', 'dietLabels', 'ingredientLines', 'calories', 'totalTime']
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
            'error' => 'No recipes found for: ' . $query
        ];
    }
    
    // Extract recipes from hits
    $recipes = [];
    foreach ($data['hits'] as $hit) {
        $recipe = $hit['recipe'];
        $recipes[] = [
            'label' => $recipe['label'] ?? 'Unknown Recipe',
            'image' => $recipe['image'] ?? '',
            'url' => $recipe['url'] ?? '',
            'dietLabels' => $recipe['dietLabels'] ?? [],
            'ingredientLines' => $recipe['ingredientLines'] ?? [],
            'calories' => isset($recipe['calories']) ? round($recipe['calories']) : 0,
            'totalTime' => $recipe['totalTime'] ?? 0
        ];
    }
    
    return [
        'success' => true,
        'data' => $recipes
    ];
}

/**
 * Display recipes in a formatted way
 * @param array $recipes Array of recipe data
 */
function displayRecipes($recipes) {
    echo "\n🍽️  RECIPE SEARCH RESULTS:\n";
    echo str_repeat('=', 60) . "\n";
    
    foreach ($recipes as $index => $recipe) {
        echo sprintf("\n%d. %s\n", $index + 1, $recipe['label']);
        echo str_repeat('-', 40) . "\n";
        
        if ($recipe['calories'] > 0) {
            echo "🔥 Calories: " . $recipe['calories'] . "\n";
        }
        
        if ($recipe['totalTime'] > 0) {
            echo "⏱️  Total Time: " . $recipe['totalTime'] . " minutes\n";
        }
        
        if (!empty($recipe['dietLabels'])) {
            echo "🥗 Diet Labels: " . implode(', ', $recipe['dietLabels']) . "\n";
        }
        
        echo "🔗 URL: " . $recipe['url'] . "\n";
        
        echo "📋 Ingredients (" . count($recipe['ingredientLines']) . "):";
        foreach ($recipe['ingredientLines'] as $i => $ingredient) {
            if ($i < 5) { // Show only first 5 ingredients
                echo "\n   • " . $ingredient;
            } elseif ($i === 5) {
                echo "\n   ... and " . (count($recipe['ingredientLines']) - 5) . " more";
                break;
            }
        }
        echo "\n";
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Total: " . count($recipes) . " recipes found\n\n";
}

/**
 * Show detailed view of a specific recipe
 * @param array $recipe Recipe data
 */
function showRecipeDetails($recipe) {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "📖 RECIPE DETAILS: " . $recipe['label'] . "\n";
    echo str_repeat('=', 60) . "\n";
    
    if ($recipe['calories'] > 0) {
        echo "🔥 Calories: " . $recipe['calories'] . "\n";
    }
    
    if ($recipe['totalTime'] > 0) {
        echo "⏱️  Total Time: " . $recipe['totalTime'] . " minutes\n";
    }
    
    if (!empty($recipe['dietLabels'])) {
        echo "🥗 Diet Labels: " . implode(', ', $recipe['dietLabels']) . "\n";
    }
    
    echo "🔗 URL: " . $recipe['url'] . "\n\n";
    
    echo "📋 COMPLETE INGREDIENTS LIST:\n";
    echo str_repeat('-', 40) . "\n";
    foreach ($recipe['ingredientLines'] as $index => $ingredient) {
        echo sprintf("%2d. %s\n", $index + 1, $ingredient);
    }
    echo str_repeat('-', 40) . "\n";
    echo "Total: " . count($recipe['ingredientLines']) . " ingredients\n\n";
}

/**
 * Interactive menu for recipe selection
 * @param array $recipes Array of recipes
 */
function interactiveMenu($recipes) {
    while (true) {
        echo "\n📋 OPTIONS:\n";
        echo "1-" . count($recipes) . ": View detailed recipe\n";
        echo "s: Save all recipes to file\n";
        echo "q: Quit\n";
        echo "\nEnter your choice: ";
        
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($input) === 'q') {
            echo "\n👋 Goodbye!\n\n";
            break;
        } elseif (strtolower($input) === 's') {
            saveRecipesToFile($recipes);
        } elseif (is_numeric($input) && $input >= 1 && $input <= count($recipes)) {
            showRecipeDetails($recipes[$input - 1]);
        } else {
            echo "\n❌ Invalid choice. Please try again.\n";
        }
    }
}

/**
 * Save recipes to a text file
 * @param array $recipes Array of recipes
 */
function saveRecipesToFile($recipes) {
    $filename = 'recipe_search_' . date('Y-m-d_H-i-s') . '.txt';
    $content = "Recipe Search Results\n";
    $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
    $content .= str_repeat('=', 60) . "\n\n";
    
    foreach ($recipes as $index => $recipe) {
        $content .= ($index + 1) . ". " . $recipe['label'] . "\n";
        $content .= str_repeat('-', 40) . "\n";
        
        if ($recipe['calories'] > 0) {
            $content .= "Calories: " . $recipe['calories'] . "\n";
        }
        
        if ($recipe['totalTime'] > 0) {
            $content .= "Total Time: " . $recipe['totalTime'] . " minutes\n";
        }
        
        if (!empty($recipe['dietLabels'])) {
            $content .= "Diet Labels: " . implode(', ', $recipe['dietLabels']) . "\n";
        }
        
        $content .= "URL: " . $recipe['url'] . "\n";
        $content .= "\nIngredients:\n";
        
        foreach ($recipe['ingredientLines'] as $i => $ingredient) {
            $content .= "  " . ($i + 1) . ". " . $ingredient . "\n";
        }
        
        $content .= "\n" . str_repeat('-', 40) . "\n\n";
    }
    
    file_put_contents($filename, $content);
    echo "\n✅ Recipes saved to: $filename\n";
}

/**
 * Main execution
 */
function main() {
    global $argv;
    
    // Check if search query is provided
    if (!isset($argv[1]) || empty(trim($argv[1]))) {
        echo "\n❌ Error: Please provide a search query\n";
        echo "Usage: php recipe_search.php \"Search Query\"\n";
        echo "Example: php recipe_search.php \"chicken curry\"\n\n";
        exit(1);
    }
    
    $query = trim($argv[1]);
    $limit = isset($argv[2]) && is_numeric($argv[2]) ? (int)$argv[2] : 10;
    
    echo "\n🔍 Searching for recipes: \"$query\"...\n";
    
    $result = searchRecipes($query, $limit);
    
    if ($result['success']) {
        displayRecipes($result['data']);
        interactiveMenu($result['data']);
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
# PHP Terminal-Based Recipe Ingredient Extractor

A standalone PHP script that extracts ingredients from recipe names using the Edamam Recipe Search API.

## Files Created

- `get_ingredients.php` - Main PHP script
- `get_ingredients.bat` - Windows batch file for easy execution
- `PHP_README.md` - This documentation file

## Prerequisites

- PHP 7.0 or higher with cURL extension enabled
- Internet connection
- Valid Edamam Recipe Search API credentials (already configured)

## Usage

### Method 1: Direct PHP Command
```bash
php get_ingredients.php "Recipe Name"
```

### Method 2: Using Batch File (Windows)
```cmd
get_ingredients.bat "Recipe Name"
```

## Examples

```bash
# Search for Chicken Biryani ingredients
php get_ingredients.php "Chicken Biryani"

# Search for Chocolate Cake ingredients
php get_ingredients.php "Chocolate Cake"

# Search for Caesar Salad ingredients
php get_ingredients.php "Caesar Salad"
```

## Features

✅ **Terminal-based interface** - No web browser required
✅ **Formatted output** - Clean, readable ingredient lists
✅ **Recipe information** - Shows recipe name, diet labels, and source URL
✅ **Save to file** - Option to save ingredients to a text file
✅ **Error handling** - Comprehensive error messages
✅ **Cross-platform** - Works on Windows, Linux, and macOS

## Output Format

```
============================================================
🍽️  RECIPE: Chicken Biryani
============================================================
🏷️  Diet Labels: Gluten-Free, Dairy-Free

📋 INGREDIENTS (12 items):
----------------------------------------
 1. 2 cups basmati rice
 2. 1 lb chicken, cut into pieces
 3. 1 large onion, sliced
 4. 2 tbsp ginger-garlic paste
 5. 1 cup yogurt
 ...

🔗 Full Recipe: https://example.com/recipe
🖼️  Image: https://example.com/image.jpg
============================================================
```

## API Configuration

The script uses your existing Edamam Recipe Search API credentials:
- App ID: `5ab9b74d`
- App Key: `0d76053275625acbab556cc56ed691f6`

## Error Handling

The script handles various error scenarios:
- Missing recipe name argument
- Network connectivity issues
- API rate limits
- No recipes found
- Invalid API responses

## File Saving Feature

After displaying ingredients, the script offers to save them to a text file:
- Automatically generates filename based on recipe name
- Includes timestamp and recipe source URL
- Saves in the same directory as the script

## Troubleshooting

### "PHP is not recognized"
- Install PHP from https://www.php.net/downloads
- Add PHP to your system PATH
- Restart your terminal/command prompt

### "cURL Error"
- Ensure cURL extension is enabled in PHP
- Check your internet connection
- Verify firewall settings

### "No recipes found"
- Try different recipe name variations
- Use more specific or common recipe names
- Check for typos in the recipe name

## Integration

This script can be easily integrated into:
- Batch processing workflows
- Meal planning applications
- Recipe management systems
- Automated cooking assistants

## License

This script uses the Edamam Recipe Search API. Please ensure you comply with Edamam's terms of service and API usage limits.
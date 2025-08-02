import requests

# Nutrition Analysis API
APP_ID = "37660c0a"
APP_KEY = "7ed9009782f89e3ef81b0d6370d08cbd"
EDAMAM_API_URL = "https://api.edamam.com/api/nutrition-details"

# Autocomplete API
FOOD_APP_ID = "9e9e1bf3"
FOOD_APP_KEY = "fbdb8c6e26a79638d29c7d81498ac515"

# Recipe Search API
RECIPE_APP_ID = "5ab9b74d"
RECIPE_APP_KEY = "0d76053275625acbab556cc56ed691f6"
RECIPE_API_URL = "https://api.edamam.com/api/recipes/v2"

NUTRIENT_THRESHOLDS = {
    "NA": 600,
    "SUGAR": 25,
    "FAT": 70
}

def analyze_ingredients(ingredients):
    payload = {
        "title": "Product Nutrition",
        "ingr": ingredients
    }

    headers = {"Content-Type": "application/json"}

    try:
        response = requests.post(
            EDAMAM_API_URL,
            params={"app_id": APP_ID, "app_key": APP_KEY},
            json=payload,
            headers=headers
        )

        if response.ok:
            data = response.json()
            return {
                "success": True,
                "data": format_result(data)
            }
        else:
            return {
                "success": False,
                "error": f"{response.status_code}: {response.text}"
            }

    except Exception as e:
        return {
            "success": False,
            "error": str(e)
        }

def format_result(api_data):
    total_nutrients = api_data.get("totalNutrients", {})
    flags = {}

    for key, limit in NUTRIENT_THRESHOLDS.items():
        if key in total_nutrients:
            value = total_nutrients[key]["quantity"]
            if value > limit:
                flags[key] = "high"
            else:
                flags[key] = "normal"

    return {
        "nutrients": total_nutrients,
        "healthLabels": api_data.get("healthLabels", []),
        "cautions": api_data.get("cautions", []),
        "flags": flags
    }

def autocomplete_ingredient(query):
    url = "https://api.edamam.com/auto-complete"
    params = {
        "q": query,
        "limit": 10,
        "app_id": FOOD_APP_ID,
        "app_key": FOOD_APP_KEY
    }
    try:
        response = requests.get(url, params=params)
        if response.ok:
            return {
                "success": True,
                "data": response.json()
            }
        else:
            return {
                "success": False,
                "error": f"{response.status_code}: {response.text}"
            }
    except Exception as e:
        return {
            "success": False,
            "error": str(e)
        }

def search_recipes(query):
    url = "https://api.edamam.com/api/recipes/v2"
    params = {
        "type": "public",
        "q": query,
        "app_id": RECIPE_APP_ID,
        "app_key": RECIPE_APP_KEY,
        "random": "false",
        "field": ["label", "image", "url", "dietLabels", "ingredientLines"]
    }

    headers = {
        "Edamam-Account-User": "test-user"  # 🔁 You can use any identifier like email or user ID
    }

    try:
        response = requests.get(url, params=params, headers=headers)
        if response.ok:
            hits = response.json().get("hits", [])
            recipes = [hit["recipe"] for hit in hits]
            return {"success": True, "data": recipes}
        else:
            return {"success": False, "error": f"{response.status_code}: {response.text}"}
    except Exception as e:
        return {"success": False, "error": str(e)}

def get_ingredients_from_recipe_name(recipe_name):
    """
    Get ingredients list from a recipe name using Edamam Recipe Search API
    Returns the first matching recipe's ingredients
    """
    result = search_recipes(recipe_name)
    
    if result["success"] and result["data"]:
        # Get the first recipe match
        first_recipe = result["data"][0]
        return {
            "success": True,
            "data": {
                "recipe_name": first_recipe.get("label", "Unknown Recipe"),
                "ingredients": first_recipe.get("ingredientLines", []),
                "image": first_recipe.get("image", ""),
                "url": first_recipe.get("url", "")
            }
        }
    else:
        return {
            "success": False,
            "error": result.get("error", "No recipes found for this name")
        }

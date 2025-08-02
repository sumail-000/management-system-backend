export interface RecipeIngredient {
  text: string;
  quantity?: number;
  measure?: string;
  food: string;
  weight: number;
  foodId?: string;
  foodCategory?: string;
  image?: string;
  nutrients: Record<string, RecipeNutrient>;
  isMainIngredient: boolean;
  allergens: string[];
}

export interface RecipeNutrient {
  label: string;
  quantity: number;
  unit: string;
}

export interface RecipeDigestItem {
  label: string;
  tag: string;
  schemaOrgTag?: string;
  total: number;
  hasRDI: boolean;
  daily: number;
  unit: string;
  sub: RecipeDigestItem[];
}

export interface RecipeImages {
  [size: string]: {
    url?: string;
    width?: number;
    height?: number;
  };
}

export interface EdamamRecipe {
  recipe: {
    uri: string;
    label: string;
    image?: string;
    images: RecipeImages;
    source?: string;
    url?: string;
    shareAs?: string;
    yield: number;
    dietLabels: string[];
    healthLabels: string[];
    cautions: string[];
    ingredientLines: string[];
    ingredients: RecipeIngredient[];
    calories: number;
    glycemicIndex?: number;
    inflammatoryIndex?: number;
    totalCO2Emissions?: number;
    co2EmissionsClass?: string;
    totalWeight: number;
    cuisineType: string[];
    mealType: string[];
    dishType: string[];
    instructions: string[];
    tags: string[];
    externalId?: string;
    totalNutrients: Record<string, RecipeNutrient>;
    totalDaily: Record<string, RecipeNutrient>;
    digest: RecipeDigestItem[];
    nutritionSummary: Record<string, any>;
    servingInfo: Record<string, any>;
    preparationInfo: Record<string, any>;
  };
  _links: {
    self?: string;
    next?: string;
  };
  relevanceScore: number;
  nutritionScore: number;
  difficultyLevel: string;
  costEstimate: string;
}

export interface RecipeSearchResponse {
  success: boolean;
  data: {
    data: EdamamRecipe[];
    meta: {
      total: number;
      searchedAt: string;
      source: string;
      version: string;
      summary: {
        totalRecipes: number;
        averageCalories: number;
        averageTime: number;
        averageYield: number;
        totalCalories: number;
        totalTime: number;
        popularCuisines: string[];
        popularMealTypes: string[];
        popularDishTypes: string[];
        commonDietLabels: string[];
        commonHealthLabels: string[];
        difficultyDistribution: Record<string, number>;
        costDistribution: Record<string, number>;
      };
    };
    filters: {
      cuisineTypes: string[];
      mealTypes: string[];
      dishTypes: string[];
      dietLabels: string[];
      healthLabels: string[];
      timeRanges: string[];
      calorieRanges: string[];
      difficulties: string[];
      costs: string[];
    };
    suggestions: Record<string, any>;
    aggregated: Record<string, any>;
  };
  pagination: {
    from: number;
    to: number;
    count: number;
  };
  meta: {
    type: string;
    search_params: Record<string, any>;
    total_results: number;
    cached: boolean;
    timestamp: string;
    request_id: string;
  };
}

export interface RecipeDetailsResponse {
  success: boolean;
  data: EdamamRecipe;
  meta: {
    uri: string;
    cached: boolean;
    timestamp: string;
    request_id: string;
  };
}

export interface RecipeSearchParams {
  q?: string;
  diet?: string;
  health?: string;
  cuisineType?: string;
  mealType?: string;
  dishType?: string;
  calories?: string;
  time?: string;
  excluded?: string;
  random?: boolean;
  from?: number;
  to?: number;
  type?: string;
}

export interface RecipeFiltersResponse {
  success: boolean;
  data: {
    diets: string[];
    health: string[];
    cuisineTypes: string[];
    mealTypes: string[];
    dishTypes: string[];
  };
}

// Frontend UI Recipe interface (simplified version)
export interface Recipe {
  id: string;
  name: string;
  image?: string;
  calories: number;
  cookTime?: number;
  servings: number;
  difficulty: string;
  diet: string[];
  ingredients: string[];
  rating?: number;
  description?: string;
  cuisine: string[];
  tags: string[];
  url?: string;
  source?: string;
}

// Helper function to transform EdamamRecipe to frontend Recipe
export function transformRecipeFromAPI(edamamRecipe: EdamamRecipe): Recipe {
  const recipe = edamamRecipe.recipe;
  
  return {
    id: recipe.uri,
    name: recipe.label,
    image: recipe.image,
    calories: Math.round(recipe.calories),
    cookTime: undefined, // Not available in Edamam API
    servings: recipe.yield,
    difficulty: edamamRecipe.difficultyLevel || 'medium',
    diet: recipe.dietLabels,
    ingredients: recipe.ingredientLines,
    rating: undefined, // Not available in Edamam API
    description: undefined, // Not available in Edamam API
    cuisine: recipe.cuisineType,
    tags: [...recipe.mealType, ...recipe.dishType, ...recipe.healthLabels],
    url: recipe.url,
    source: recipe.source
  };
}

// Helper function to transform search params for API
export function transformSearchParamsToAPI(params: {
  query?: string;
  diet?: string;
  cuisine?: string;
  difficulty?: string;
  limit?: number;
  page?: number;
}): RecipeSearchParams {
  const apiParams: RecipeSearchParams = {
    type: 'public'
  };

  if (params.query) {
    apiParams.q = params.query;
  }

  if (params.diet) {
    apiParams.diet = params.diet;
  }

  if (params.cuisine) {
    apiParams.cuisineType = params.cuisine;
  }

  if (params.limit) {
    const from = ((params.page || 1) - 1) * params.limit;
    const to = from + params.limit;
    apiParams.from = from;
    apiParams.to = to;
  }

  return apiParams;
}
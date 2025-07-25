<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EdamamErrorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'error' => [
                'type' => $this->resource['type'] ?? 'api_error',
                'message' => $this->resource['message'] ?? 'An error occurred while processing your request.',
                'code' => $this->resource['code'] ?? 'UNKNOWN_ERROR',
                'details' => $this->resource['details'] ?? null,
                'timestamp' => $this->resource['timestamp'] ?? now()->toISOString(),
                'requestId' => $this->resource['requestId'] ?? null,
                'suggestions' => $this->generateSuggestions(),
                'documentation' => $this->getDocumentationLinks()
            ],
            'meta' => [
                'service' => 'Edamam API Integration',
                'version' => '2.0',
                'supportContact' => 'support@example.com'
            ]
        ];
    }

    /**
     * Generate helpful suggestions based on error type.
     */
    private function generateSuggestions(): array
    {
        $errorCode = $this->resource['code'] ?? 'UNKNOWN_ERROR';
        $suggestions = [];
        
        switch ($errorCode) {
            case 'INVALID_INGREDIENT':
                $suggestions = [
                    'Check the spelling of your ingredient',
                    'Try using more generic terms (e.g., "chicken" instead of "organic free-range chicken")',
                    'Use common ingredient names in English',
                    'Avoid brand names and focus on the actual food item'
                ];
                break;
                
            case 'API_QUOTA_EXCEEDED':
                $suggestions = [
                    'Wait for your API quota to reset',
                    'Consider upgrading your API plan',
                    'Reduce the frequency of API calls',
                    'Implement caching to reduce API usage'
                ];
                break;
                
            case 'INVALID_API_CREDENTIALS':
                $suggestions = [
                    'Verify your API credentials in the .env file',
                    'Check if your API subscription is active',
                    'Ensure you\'re using the correct API endpoint',
                    'Contact Edamam support if credentials are correct'
                ];
                break;
                
            case 'NETWORK_ERROR':
                $suggestions = [
                    'Check your internet connection',
                    'Try the request again in a few moments',
                    'Verify that Edamam API services are operational',
                    'Check if your firewall is blocking the request'
                ];
                break;
                
            case 'INVALID_PARAMETERS':
                $suggestions = [
                    'Review the API documentation for correct parameter formats',
                    'Check that all required parameters are provided',
                    'Verify parameter values are within acceptable ranges',
                    'Ensure parameter types match the expected format'
                ];
                break;
                
            case 'RECIPE_NOT_FOUND':
                $suggestions = [
                    'Verify the recipe URI is correct and complete',
                    'Check if the recipe is still available in the database',
                    'Try searching for the recipe using different terms',
                    'Ensure the recipe URI hasn\'t been modified'
                ];
                break;
                
            case 'FOOD_NOT_FOUND':
                $suggestions = [
                    'Try using more common food names',
                    'Check the spelling of the food item',
                    'Use generic terms instead of brand names',
                    'Try searching with partial matches'
                ];
                break;
                
            case 'RATE_LIMIT_EXCEEDED':
                $suggestions = [
                    'Reduce the frequency of your requests',
                    'Implement exponential backoff in your retry logic',
                    'Consider batching multiple requests',
                    'Wait before making additional requests'
                ];
                break;
                
            case 'VALIDATION_ERROR':
                $suggestions = [
                    'Check that all required fields are provided',
                    'Verify data types match the expected format',
                    'Ensure values are within acceptable ranges',
                    'Review the validation rules for each field'
                ];
                break;
                
            default:
                $suggestions = [
                    'Try the request again in a few moments',
                    'Check the API documentation for guidance',
                    'Verify your request parameters are correct',
                    'Contact support if the problem persists'
                ];
                break;
        }
        
        return $suggestions;
    }

    /**
     * Get relevant documentation links.
     */
    private function getDocumentationLinks(): array
    {
        $errorCode = $this->resource['code'] ?? 'UNKNOWN_ERROR';
        $links = [
            'general' => 'https://developer.edamam.com/edamam-docs-nutrition-api',
            'support' => 'https://developer.edamam.com/admin'
        ];
        
        switch ($errorCode) {
            case 'INVALID_INGREDIENT':
            case 'FOOD_NOT_FOUND':
                $links['food_database'] = 'https://developer.edamam.com/food-database-api-docs';
                break;
                
            case 'RECIPE_NOT_FOUND':
                $links['recipe_search'] = 'https://developer.edamam.com/edamam-docs-recipe-api';
                break;
                
            case 'INVALID_API_CREDENTIALS':
                $links['authentication'] = 'https://developer.edamam.com/admin';
                break;
                
            case 'API_QUOTA_EXCEEDED':
            case 'RATE_LIMIT_EXCEEDED':
                $links['pricing'] = 'https://developer.edamam.com/edamam-nutrition-api';
                break;
        }
        
        return $links;
    }

    /**
     * Create error resource from exception.
     */
    public static function fromException(\Exception $exception, ?string $requestId = null): self
    {
        $errorData = [
            'type' => 'exception',
            'message' => $exception->getMessage(),
            'code' => $exception->getCode() ?: 'EXCEPTION_ERROR',
            'details' => [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => config('app.debug') ? $exception->getTraceAsString() : null
            ],
            'timestamp' => now()->toISOString(),
            'requestId' => $requestId
        ];
        
        return new self($errorData);
    }

    /**
     * Create error resource from API response.
     */
    public static function fromApiResponse(array $apiError, ?string $requestId = null): self
    {
        $errorData = [
            'type' => 'api_error',
            'message' => $apiError['message'] ?? 'API request failed',
            'code' => $apiError['errorCode'] ?? $apiError['code'] ?? 'API_ERROR',
            'details' => $apiError['details'] ?? $apiError,
            'timestamp' => now()->toISOString(),
            'requestId' => $requestId
        ];
        
        return new self($errorData);
    }

    /**
     * Create error resource from validation errors.
     */
    public static function fromValidationErrors(array $errors, ?string $requestId = null): self
    {
        $errorData = [
            'type' => 'validation_error',
            'message' => 'The request contains invalid data',
            'code' => 'VALIDATION_ERROR',
            'details' => [
                'errors' => $errors,
                'fields' => array_keys($errors)
            ],
            'timestamp' => now()->toISOString(),
            'requestId' => $requestId
        ];
        
        return new self($errorData);
    }

    /**
     * Create error resource for network errors.
     */
    public static function networkError(string $message = 'Network error occurred', ?string $requestId = null): self
    {
        $errorData = [
            'type' => 'network_error',
            'message' => $message,
            'code' => 'NETWORK_ERROR',
            'details' => [
                'suggestion' => 'Check your internet connection and try again'
            ],
            'timestamp' => now()->toISOString(),
            'requestId' => $requestId
        ];
        
        return new self($errorData);
    }

    /**
     * Create error resource for quota exceeded.
     */
    public static function quotaExceeded(?string $requestId = null): self
    {
        $errorData = [
            'type' => 'quota_error',
            'message' => 'API quota has been exceeded',
            'code' => 'API_QUOTA_EXCEEDED',
            'details' => [
                'suggestion' => 'Wait for quota reset or upgrade your plan'
            ],
            'timestamp' => now()->toISOString(),
            'requestId' => $requestId
        ];
        
        return new self($errorData);
    }
}
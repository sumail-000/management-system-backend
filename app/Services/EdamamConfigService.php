<?php

namespace App\Services;

use InvalidArgumentException;

class EdamamConfigService
{
    /**
     * Get Nutrition Analysis API configuration
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getNutritionConfig(): array
    {
        $appId = config('services.edamam.nutrition.app_id');
        $appKey = config('services.edamam.nutrition.app_key');
        $apiUrl = config('services.edamam.nutrition.api_url');

        $this->validateConfig($appId, $appKey, $apiUrl, 'Nutrition Analysis');

        return [
            'app_id' => $appId,
            'app_key' => $appKey,
            'api_url' => $apiUrl,
        ];
    }

    /**
     * Get Food Database API configuration
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getFoodConfig(): array
    {
        $appId = config('services.edamam.food.app_id');
        $appKey = config('services.edamam.food.app_key');
        $apiUrl = config('services.edamam.food.api_url');

        $this->validateConfig($appId, $appKey, $apiUrl, 'Food Database');

        return [
            'app_id' => $appId,
            'app_key' => $appKey,
            'api_url' => $apiUrl,
        ];
    }

    /**
     * Get Recipe Search API configuration
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getRecipeConfig(): array
    {
        $appId = config('services.edamam.recipe.app_id');
        $appKey = config('services.edamam.recipe.app_key');
        $apiUrl = config('services.edamam.recipe.api_url');

        $this->validateConfig($appId, $appKey, $apiUrl, 'Recipe Search');

        return [
            'app_id' => $appId,
            'app_key' => $appKey,
            'api_url' => $apiUrl,
        ];
    }

    /**
     * Get all Edamam API configurations
     *
     * @return array
     */
    public function getAllConfigs(): array
    {
        return [
            'nutrition' => $this->getNutritionConfig(),
            'food' => $this->getFoodConfig(),
            'recipe' => $this->getRecipeConfig(),
        ];
    }

    /**
     * Check if all Edamam APIs are properly configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        try {
            $this->getAllConfigs();
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Get configuration status for each API
     *
     * @return array
     */
    public function getConfigStatus(): array
    {
        $status = [];

        try {
            $this->getNutritionConfig();
            $status['nutrition'] = true;
        } catch (InvalidArgumentException $e) {
            $status['nutrition'] = false;
        }

        try {
            $this->getFoodConfig();
            $status['food'] = true;
        } catch (InvalidArgumentException $e) {
            $status['food'] = false;
        }

        try {
            $this->getRecipeConfig();
            $status['recipe'] = true;
        } catch (InvalidArgumentException $e) {
            $status['recipe'] = false;
        }

        return $status;
    }

    /**
     * Validate API configuration
     *
     * @param string|null $appId
     * @param string|null $appKey
     * @param string|null $apiUrl
     * @param string $apiName
     * @throws InvalidArgumentException
     */
    private function validateConfig(?string $appId, ?string $appKey, ?string $apiUrl, string $apiName): void
    {
        if (empty($appId)) {
            throw new InvalidArgumentException("Edamam {$apiName} API ID is not configured");
        }

        if (empty($appKey)) {
            throw new InvalidArgumentException("Edamam {$apiName} API Key is not configured");
        }

        if (empty($apiUrl)) {
            throw new InvalidArgumentException("Edamam {$apiName} API URL is not configured");
        }

        if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Edamam {$apiName} API URL is not a valid URL");
        }
    }

    /**
     * Get default request headers for Edamam API
     *
     * @return array
     */
    public function getDefaultHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Laravel-Food-Management-System/1.0',
        ];

        // Add Edamam-Account-User header as required by the API
        $userId = config('services.edamam.user_id');
        if (!empty($userId)) {
            $headers['Edamam-Account-User'] = $userId;
        }

        return $headers;
    }

    /**
     * Get request timeout configuration
     *
     * @return int
     */
    public function getRequestTimeout(): int
    {
        return config('services.edamam.timeout', 30);
    }

    /**
     * Get retry configuration
     *
     * @return array
     */
    public function getRetryConfig(): array
    {
        return [
            'max_retries' => config('services.edamam.max_retries', 3),
            'retry_delay' => config('services.edamam.retry_delay', 1000), // milliseconds
        ];
    }
}
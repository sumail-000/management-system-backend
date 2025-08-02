<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
    /**
     * Store frontend logs in backend
     */
    public function storeFrontendLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level' => 'required|string|in:debug,info,warning,error',
            'message' => 'required|string|max:1000',
            'context' => 'nullable|array',
            'timestamp' => 'required|string',
            'source' => 'required|string|in:frontend,react',
            'component' => 'nullable|string|max:100',
            'user_id' => 'nullable|integer',
            'session_id' => 'nullable|string|max:100'
        ]);

        // Add request metadata
        $logData = array_merge($validated['context'] ?? [], [
            'frontend_timestamp' => $validated['timestamp'],
            'source' => $validated['source'],
            'component' => $validated['component'] ?? null,
            'user_id' => $validated['user_id'] ?? $request->user()?->id,
            'session_id' => $validated['session_id'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'backend_timestamp' => now()->toISOString()
        ]);

        // Log to appropriate channel based on component
        $channel = $this->getLogChannel($validated['component'] ?? null);
        
        // Log with appropriate level
        switch ($validated['level']) {
            case 'debug':
                Log::channel($channel)->debug('[FRONTEND] ' . $validated['message'], $logData);
                break;
            case 'info':
                Log::channel($channel)->info('[FRONTEND] ' . $validated['message'], $logData);
                break;
            case 'warning':
                Log::channel($channel)->warning('[FRONTEND] ' . $validated['message'], $logData);
                break;
            case 'error':
                Log::channel($channel)->error('[FRONTEND] ' . $validated['message'], $logData);
                break;
        }

        return response()->json([
            'message' => 'Log stored successfully',
            'logged_at' => now()->toISOString()
        ]);
    }

    /**
     * Get appropriate log channel based on component
     */
    private function getLogChannel(?string $component): string
    {
        return match($component) {
            'AuthContext', 'Login', 'Register', 'auth' => 'auth',
            'api', 'axios' => 'single',
            default => 'single'
        };
    }

    /**
     * Batch store multiple frontend logs
     */
    public function storeBatchFrontendLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'logs' => 'required|array|max:50',
            'logs.*.level' => 'required|string|in:debug,info,warning,error',
            'logs.*.message' => 'required|string|max:1000',
            'logs.*.context' => 'nullable|array',
            'logs.*.timestamp' => 'required|string',
            'logs.*.source' => 'required|string|in:frontend,react',
            'logs.*.component' => 'nullable|string|max:100',
            'logs.*.user_id' => 'nullable|integer',
            'logs.*.session_id' => 'nullable|string|max:100'
        ]);

        $processedCount = 0;
        
        foreach ($validated['logs'] as $logEntry) {
            try {
                // Process each log entry similar to single log
                $logData = array_merge($logEntry['context'] ?? [], [
                    'frontend_timestamp' => $logEntry['timestamp'],
                    'source' => $logEntry['source'],
                    'component' => $logEntry['component'] ?? null,
                    'user_id' => $logEntry['user_id'] ?? $request->user()?->id,
                    'session_id' => $logEntry['session_id'] ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'backend_timestamp' => now()->toISOString()
                ]);

                $channel = $this->getLogChannel($logEntry['component'] ?? null);
                
                switch ($logEntry['level']) {
                    case 'debug':
                        Log::channel($channel)->debug('[FRONTEND] ' . $logEntry['message'], $logData);
                        break;
                    case 'info':
                        Log::channel($channel)->info('[FRONTEND] ' . $logEntry['message'], $logData);
                        break;
                    case 'warning':
                        Log::channel($channel)->warning('[FRONTEND] ' . $logEntry['message'], $logData);
                        break;
                    case 'error':
                        Log::channel($channel)->error('[FRONTEND] ' . $logEntry['message'], $logData);
                        break;
                }
                
                $processedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to process frontend log entry', [
                    'error' => $e->getMessage(),
                    'log_entry' => $logEntry
                ]);
            }
        }

        return response()->json([
            'message' => 'Batch logs processed',
            'processed_count' => $processedCount,
            'total_count' => count($validated['logs']),
            'logged_at' => now()->toISOString()
        ]);
    }
}
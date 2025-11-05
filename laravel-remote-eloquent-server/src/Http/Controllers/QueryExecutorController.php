<?php

namespace RemoteEloquent\Server\Http\Controllers;

use RemoteEloquent\Server\QueryExecutor;
use RemoteEloquent\Server\Exceptions\QueryExecutionException;
use RemoteEloquent\Server\Exceptions\SecurityException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Query Executor Controller
 *
 * API endpoint that receives remote query requests and executes them.
 */
class QueryExecutorController extends Controller
{
    /**
     * Query executor instance
     *
     * @var QueryExecutor
     */
    protected QueryExecutor $queryExecutor;

    /**
     * Create a new controller instance
     *
     * @param QueryExecutor $queryExecutor
     */
    public function __construct(QueryExecutor $queryExecutor)
    {
        $this->queryExecutor = $queryExecutor;

        // Apply authentication middleware if configured
        if (config('remote-eloquent.security.require_auth', true)) {
            $this->middleware('auth:sanctum');
        }

        // Apply rate limiting if configured
        if (config('remote-eloquent.security.rate_limiting.enabled', true)) {
            $limit = config('remote-eloquent.security.rate_limiting.limit', 100);
            $this->middleware("throttle:{$limit},1");
        }
    }

    /**
     * Execute a remote query
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function execute(Request $request): JsonResponse
    {
        try {
            // Validate request structure
            $validated = $request->validate([
                'model' => 'required|string',
                'chain' => 'required|array',
                'method' => 'required|string',
                'parameters' => 'sometimes|array',
                'metadata' => 'sometimes|array',
            ]);

            // Execute query
            $result = $this->queryExecutor->execute(
                $validated,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'metadata' => [
                    'server_version' => $this->getServerVersion(),
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

        } catch (SecurityException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'type' => 'SecurityException',
            ], $e->getStatusCode());

        } catch (QueryExecutionException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'type' => 'QueryExecutionException',
            ], 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid request format',
                'errors' => $e->errors(),
                'type' => 'ValidationException',
            ], 422);

        } catch (\Exception $e) {
            // Log unexpected errors
            logger()->error('Remote query execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'An error occurred while executing the query',
                'type' => 'Exception',
            ], 500);
        }
    }

    /**
     * Get server version
     *
     * @return string
     */
    protected function getServerVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Health check endpoint
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'version' => $this->getServerVersion(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

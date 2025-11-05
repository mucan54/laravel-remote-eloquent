<?php

namespace RemoteEloquent\Server\Http\Controllers;

use RemoteEloquent\Server\QueryExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Remote Eloquent Controller
 *
 * API endpoint for executing remote queries.
 */
class RemoteEloquentController extends Controller
{
    public function __construct()
    {
        // Apply authentication middleware from config
        $authMiddleware = config('remote-eloquent.auth_middleware');

        if ($authMiddleware !== null) {
            // Support both string and array of middleware
            if (is_array($authMiddleware)) {
                foreach ($authMiddleware as $middleware) {
                    $this->middleware($middleware);
                }
            } else {
                $this->middleware($authMiddleware);
            }
        }

        // Rate limiting
        $this->middleware('throttle:100,1');
    }

    /**
     * Execute remote query
     *
     * @param Request $request
     * @param QueryExecutor $executor
     * @return JsonResponse
     */
    public function execute(Request $request, QueryExecutor $executor): JsonResponse
    {
        try {
            $validated = $request->validate([
                'model' => 'required|string',
                'chain' => 'required|array',
                'method' => 'required|string',
                'parameters' => 'sometimes|array',
            ]);

            $result = $executor->execute($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Execute batch queries
     *
     * @param Request $request
     * @param QueryExecutor $executor
     * @return JsonResponse
     */
    public function batch(Request $request, QueryExecutor $executor): JsonResponse
    {
        try {
            $validated = $request->validate([
                'queries' => 'required|array',
                'queries.*' => 'required|array',
            ]);

            // Check batch limit
            $maxBatch = config('remote-eloquent.batch.max_queries', 10);
            if (count($validated['queries']) > $maxBatch) {
                return response()->json([
                    'success' => false,
                    'error' => "Batch limit exceeded. Maximum {$maxBatch} queries allowed.",
                ], 400);
            }

            $results = [];
            $errors = [];

            // Execute each query
            foreach ($validated['queries'] as $key => $ast) {
                try {
                    $results[$key] = $executor->execute($ast);
                } catch (\Exception $e) {
                    $results[$key] = ['error' => $e->getMessage()];
                    $errors[] = $key;
                }
            }

            return response()->json([
                'success' => empty($errors),
                'data' => $results,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Health check
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'mode' => config('remote-eloquent.mode', 'server'),
        ]);
    }
}

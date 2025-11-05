<?php

namespace RemoteEloquent\Server\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Remote Eloquent Middleware
 *
 * Provides logging, security checks, and request validation for remote queries.
 */
class RemoteEloquentMiddleware
{
    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if remote eloquent is enabled
        if (!config('remote-eloquent.enabled', true)) {
            return response()->json([
                'success' => false,
                'error' => 'Remote Eloquent is currently disabled',
            ], 503);
        }

        // Log request if enabled
        if (config('remote-eloquent.logging.log_requests', false)) {
            Log::info('Remote query request received', [
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'model' => $request->input('model'),
                'method' => $request->input('method'),
            ]);
        }

        // Check IP whitelist if configured
        if ($this->shouldCheckIpWhitelist()) {
            if (!$this->isIpAllowed($request->ip())) {
                return response()->json([
                    'success' => false,
                    'error' => 'IP address not allowed',
                ], 403);
            }
        }

        $response = $next($request);

        // Log response if enabled
        if (config('remote-eloquent.logging.log_responses', false)) {
            Log::info('Remote query response sent', [
                'status' => $response->status(),
                'user_id' => $request->user()?->id,
            ]);
        }

        return $response;
    }

    /**
     * Check if IP whitelist should be enforced
     *
     * @return bool
     */
    protected function shouldCheckIpWhitelist(): bool
    {
        $whitelist = config('remote-eloquent.security.ip_whitelist', []);
        return !empty($whitelist);
    }

    /**
     * Check if IP is allowed
     *
     * @param string $ip
     * @return bool
     */
    protected function isIpAllowed(string $ip): bool
    {
        $whitelist = config('remote-eloquent.security.ip_whitelist', []);

        if (empty($whitelist)) {
            return true;
        }

        return in_array($ip, $whitelist, true);
    }
}

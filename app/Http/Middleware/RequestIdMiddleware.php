<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID') ?: (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);
        $startedAt = microtime(true);

        if ((bool) config('feed.request_logging', true)) {
            Log::info('[Request] Started', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
        }

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        if ((bool) config('feed.request_logging', true)) {
            Log::info('[Request] Completed', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => max(0, (int) ((microtime(true) - $startedAt) * 1000)),
            ]);
        }

        return $response;
    }
}

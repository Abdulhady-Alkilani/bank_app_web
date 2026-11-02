<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer service token for the ML service (fraud.ml_ingest_token). With no token configured the endpoint is closed.
 */
class VerifyMlIngestToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('fraud.ml_ingest_token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Invalid service token.', 'code' => 'invalid_token'], 401);
        }

        return $next($request);
    }
}

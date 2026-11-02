<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Support\ApiSignature;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates external bank/simulator calls.
 * Headers: X-Api-Key (api_clients.key_id), X-Timestamp (unix seconds, +-tolerance), X-Signature
 * (hex hmac_sha256(secret, timestamp . "." . rawBody)).
 */
class VerifyApiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('X-Api-Key', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $signature = (string) $request->header('X-Signature', '');

        if ($key === '' || $timestamp === '' || $signature === '') {
            return $this->reject('missing_headers', 'X-Api-Key, X-Timestamp and X-Signature headers are required.');
        }

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > (int) config('fraud.hmac_tolerance_seconds', 300)) {
            return $this->reject('timestamp_out_of_range', 'Request timestamp is outside the allowed window.');
        }

        $client = ApiClient::where('key_id', $key)->first();
        if (! $client || ! $client->is_active || $client->revoked_at !== null) {
            return $this->reject('invalid_client', 'Unknown, inactive or revoked API client.');
        }

        if (! ApiSignature::verify((string) $client->secret, $timestamp, $request->getContent(), $signature)) {
            return $this->reject('invalid_signature', 'Signature mismatch.');
        }

        $client->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_client', $client);

        return $next($request);
    }

    private function reject(string $code, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], 401);
    }
}

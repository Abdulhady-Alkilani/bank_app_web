<?php

namespace App\Support;

/**
 * HMAC-SHA256 request signing shared by the API middleware, tests, the simulator and the bank webhook.
 * signature = hex(hmac_sha256(secret, timestamp . "." . rawBody))
 */
class ApiSignature
{
    public static function sign(string $secret, int|string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function verify(string $secret, int|string $timestamp, string $body, string $signature): bool
    {
        return hash_equals(self::sign($secret, $timestamp, $body), strtolower($signature));
    }

    /**
     * Headers to attach to an external API request.
     *
     * @return array{X-Api-Key: string, X-Timestamp: string, X-Signature: string}
     */
    public static function headers(string $keyId, string $secret, string $body, ?int $timestamp = null): array
    {
        $timestamp ??= time();

        return [
            'X-Api-Key' => $keyId,
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => self::sign($secret, $timestamp, $body),
        ];
    }
}

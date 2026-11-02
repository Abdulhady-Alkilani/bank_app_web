<?php

namespace App\Services\Bank;

use App\Support\ApiSignature;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes action commands (block | freeze | require_otp) to the bank. When no webhook URL is configured
 * the command is only logged. Requests are HMAC signed: X-Timestamp and X-Signature = hmac(secret, ts.body).
 */
class BankWebhookNotifier
{
    /**
     * @param  array<string, mixed>  $data
     * @return bool true when delivered (or merely logged because no URL is configured)
     */
    public function notify(string $command, array $data = []): bool
    {
        $payload = ['command' => $command, 'issued_at' => now()->toIso8601String()] + $data;
        $url = config('fraud.bank_webhook_url');

        if (blank($url)) {
            Log::info('Bank webhook (no URL configured)', $payload);

            return true;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $secret = (string) (config('fraud.bank_webhook_secret') ?: config('app.key'));

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-Timestamp' => (string) $timestamp,
                    'X-Signature' => ApiSignature::sign($secret, $timestamp, $body),
                    'Content-Type' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Bank webhook rejected', ['command' => $command, 'status' => $response->status()]);
        } catch (Throwable $e) {
            Log::warning('Bank webhook failed', ['command' => $command, 'error' => $e->getMessage()]);
        }

        return false;
    }
}

<?php

namespace App\Services\Ai;

use App\Contracts\AiScoringClient;
use App\Data\AiScoreResult;
use App\Exceptions\AiServiceUnavailable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpAiScoringClient implements AiScoringClient
{
    public function score(array $features, array $context): AiScoreResult
    {
        try {
            $response = $this->request()->post('/v1/score', ['features' => $features, 'context' => $context]);
        } catch (Throwable $e) {
            throw new AiServiceUnavailable('AI service unreachable: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new AiServiceUnavailable('AI service returned HTTP '.$response->status());
        }

        $probability = $response->json('probability');
        if (! is_numeric($probability)) {
            throw new AiServiceUnavailable('AI service returned an invalid payload.');
        }

        return new AiScoreResult(
            probability: max(0.0, min(1.0, (float) $probability)),
            modelName: $response->json('model_name'),
            modelVersion: $response->json('model_version'),
            topFeatures: array_values((array) $response->json('top_features', [])),
            latencyMs: (int) $response->json('latency_ms', 0),
        );
    }

    public function health(): bool
    {
        try {
            return $this->request()->get('/health')->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function request(): PendingRequest
    {
        $seconds = max(0.05, (int) config('fraud.ai.timeout_ms', 250) / 1000);

        $request = Http::baseUrl(rtrim((string) config('fraud.ai.url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout($seconds)
            ->timeout($seconds);

        if ($token = config('fraud.ai.token')) {
            $request = $request->withToken($token);
        }

        return $request;
    }
}

<?php

namespace App\Services\Simulation;

use App\Contracts\ScoringPipeline;
use App\Models\ApiClient;
use App\Models\Transaction;
use App\Support\ApiSignature;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sends simulated events to the scoring engine, either by calling the pipeline service directly ("pipeline")
 * or through the signed public API ("http", POST {APP_URL}/api/v1/score).
 */
class SimulationScorer
{
    public const PIPELINE = 'pipeline';

    public const HTTP = 'http';

    private ?ApiClient $client = null;

    public function __construct(private readonly string $mode = self::PIPELINE)
    {
        if (! in_array($mode, [self::PIPELINE, self::HTTP], true)) {
            throw new RuntimeException("Unknown mode [{$mode}]; use pipeline or http.");
        }
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{score: int, decision: string, reasons: array<int, array<string, mixed>>, otp_challenge_id: int|null, subject_type: string, subject_id: int|null, risk_score_id: int|null}
     */
    public function score(array $payload): array
    {
        return $this->mode === self::HTTP ? $this->viaHttp($payload) : $this->viaPipeline($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{score: int, decision: string, reasons: array<int, array<string, mixed>>, otp_challenge_id: int|null, subject_type: string, subject_id: int|null, risk_score_id: int|null}
     */
    private function viaPipeline(array $payload): array
    {
        $result = app(ScoringPipeline::class)->process($payload);

        return [
            'score' => $result->score,
            'decision' => $result->decision->value,
            'reasons' => $result->reasons,
            'otp_challenge_id' => $result->otpChallengeId,
            'subject_type' => $result->subject instanceof Transaction ? 'transaction' : 'event',
            'subject_id' => $result->subject->getKey(),
            'risk_score_id' => $result->riskScore->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{score: int, decision: string, reasons: array<int, array<string, mixed>>, otp_challenge_id: int|null, subject_type: string, subject_id: int|null, risk_score_id: int|null}
     */
    private function viaHttp(array $payload): array
    {
        $client = $this->client();
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = Http::timeout(60)
            ->acceptJson()
            ->withBody($body, 'application/json')
            ->withHeaders(ApiSignature::headers($client->key_id, (string) $client->secret, $body))
            ->post(rtrim((string) config('app.url'), '/').'/api/v1/score');

        if (! $response->successful()) {
            throw new RuntimeException('POST /api/v1/score failed ('.$response->status().'): '.Str::limit($response->body(), 300));
        }

        $json = $response->json();

        return [
            'score' => (int) $json['score'],
            'decision' => (string) $json['decision'],
            'reasons' => (array) ($json['reasons'] ?? []),
            'otp_challenge_id' => $json['otp_challenge_id'] ?? null,
            'subject_type' => (string) ($json['subject_type'] ?? 'event'),
            'subject_id' => $json['subject_id'] ?? null,
            'risk_score_id' => $json['risk_score_id'] ?? null,
        ];
    }

    private function client(): ApiClient
    {
        return $this->client ??= ApiClient::query()->where('name', 'Simulator')->where('is_active', true)->whereNull('revoked_at')->first()
            ?? ApiClient::create([
                'name' => 'Simulator',
                'key_id' => 'key_'.Str::random(16),
                'secret' => bin2hex(random_bytes(24)),
                'is_active' => true,
            ]);
    }
}

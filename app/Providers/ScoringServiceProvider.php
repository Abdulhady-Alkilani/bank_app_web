<?php

namespace App\Providers;

use App\Contracts\AiScoringClient;
use App\Contracts\ScoringPipeline;
use App\Services\Ai\DisabledAiScoringClient;
use App\Services\Ai\FakeAiScoringClient;
use App\Services\Ai\HttpAiScoringClient;
use App\Services\Scoring\DefaultScoringPipeline;
use App\Services\Scoring\ModelRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class ScoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ModelRegistry::class);

        $this->app->bind(AiScoringClient::class, fn ($app) => match (config('fraud.ai.driver')) {
            'http' => $app->make(HttpAiScoringClient::class),
            'none', 'disabled', 'off' => $app->make(DisabledAiScoringClient::class),
            default => $app->make(FakeAiScoringClient::class),
        });

        $this->app->bind(ScoringPipeline::class, DefaultScoringPipeline::class);
    }

    public function boot(): void
    {
        RateLimiter::for('external-api', function (Request $request) {
            $client = $request->attributes->get('api_client');

            return Limit::perMinute((int) config('fraud.api_rate_limit', 600))
                ->by($client ? 'client:'.$client->getKey() : 'ip:'.$request->ip());
        });
    }
}

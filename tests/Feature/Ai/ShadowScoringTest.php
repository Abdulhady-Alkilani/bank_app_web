<?php

require_once __DIR__.'/../Scoring/Helpers.php';

use App\Contracts\ScoringPipeline;
use App\Jobs\ShadowScoreRiskScore;
use App\Models\MlModel;
use App\Models\ModelRun;
use App\Models\RiskScore;
use App\Services\Scoring\ModelRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    scoringSeed();
    config(['fraud.ai.driver' => 'http', 'fraud.ai.url' => 'http://ai.test', 'fraud.ai.token' => 'tok']);
});

it('does not dispatch the shadow job unless shadow mode is enabled', function () {
    Queue::fake();
    Http::fake(['ai.test/v1/score' => Http::response(['probability' => 0.1])]);
    ['account' => $account, 'device' => $device] = scoringCustomer();

    app(ScoringPipeline::class)->process(scoringTransfer($account, $device, 100));

    Queue::assertNotPushed(ShadowScoreRiskScore::class);
});

it('dispatches the shadow job after the risk score is stored, even when the main AI failed', function (bool $mainAnswers) {
    Queue::fake();
    config(['fraud.ai.shadow_enabled' => true]);
    Http::fake(['ai.test/v1/score' => $mainAnswers ? Http::response(['probability' => 0.1]) : Http::response([], 500)]);
    ['account' => $account, 'device' => $device] = scoringCustomer();

    $result = app(ScoringPipeline::class)->process(scoringTransfer($account, $device, 100));

    Queue::assertPushed(ShadowScoreRiskScore::class, fn (ShadowScoreRiskScore $job) => $job->riskScoreId === $result->riskScore->id && isset($job->features['amount']));
})->with([true, false]);

it('stores the shadow probability and model run without touching the decision', function () {
    $run = ModelRun::factory()->create(['model_id' => MlModel::factory()->create(['model_name' => 'lgbm-shadow']), 'version_number' => 'v0.9']);
    $score = RiskScore::factory()->create(['score' => 20, 'model_probability' => 0.1]);
    Http::fake(['ai.test/v1/shadow/score' => Http::response(['probability' => 0.8765, 'model_name' => 'lgbm-shadow', 'model_version' => 'v0.9'])]);

    (new ShadowScoreRiskScore($score->id, ['amount' => 10], ['event_type' => 'transfer']))->handle(app(ModelRegistry::class));

    $score->refresh();
    expect((float) $score->shadow_probability)->toBe(0.8765)
        ->and($score->shadow_model_run_id)->toBe($run->id)
        ->and($score->score)->toBe(20)
        ->and((float) $score->model_probability)->toBe(0.1);

    Http::assertSent(fn (Request $r) => $r->url() === 'http://ai.test/v1/shadow/score'
        && $r->hasHeader('Authorization', 'Bearer tok')
        && $r['features'] === ['amount' => 10]
        && $r['context']['event_type'] === 'transfer');
});

it('ignores 404s and connection errors', function (string $mode) {
    $score = RiskScore::factory()->create();
    $mode === '404'
        ? Http::fake(['*' => Http::response(['detail' => 'nope'], 404)])
        : Http::fake(fn () => throw new ConnectionException('timeout'));

    (new ShadowScoreRiskScore($score->id, [], []))->handle(app(ModelRegistry::class));

    expect($score->fresh()->shadow_probability)->toBeNull();
})->with(['404', 'connection']);

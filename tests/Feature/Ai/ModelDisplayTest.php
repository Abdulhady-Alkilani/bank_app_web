<?php

require_once __DIR__.'/../Analyst/helpers.php';

use App\Livewire\Analyst\AlertShow;
use App\Mcp\Servers\FraudInvestigationServer;
use App\Mcp\Tools\ExplainScoreTool;
use App\Models\MlModel;
use App\Models\ModelRun;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

it('shows the model name, version and shadow probability on the alert page', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $run = ModelRun::factory()->create(['model_id' => MlModel::factory()->create(['model_name' => 'xgboost-fraud']), 'version_number' => 'v2026.10.1']);
    $shadowRun = ModelRun::factory()->create(['model_id' => MlModel::factory()->create(['model_name' => 'lgbm-shadow']), 'version_number' => 'v0.9']);
    $alert->riskScore->update(['model_run_id' => $run->id, 'shadow_probability' => 0.4321, 'shadow_model_run_id' => $shadowRun->id]);

    Livewire::actingAs($analyst)->test(AlertShow::class, ['alert' => $alert])
        ->assertSee('xgboost-fraud')->assertSee('v2026.10.1')
        ->assertSee(__('analyst.alert.shadow_probability'))->assertSee('43%')->assertSee('lgbm-shadow');
});

it('shows rules only when no model produced the score or the fallback was used', function (bool $usedFallback) {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $run = ModelRun::factory()->create(['model_id' => MlModel::factory()->create(['model_name' => 'xgboost-fraud'])]);
    $alert->riskScore->update(['model_run_id' => $usedFallback ? $run->id : null, 'used_fallback' => $usedFallback]);

    Livewire::actingAs($analyst)->test(AlertShow::class, ['alert' => $alert])
        ->assertSee(__('analyst.alert.rules_only'))->assertDontSee('xgboost-fraud')
        ->assertDontSee(__('analyst.alert.shadow_probability'));
})->with([false, true]);

it('names the model and shadow probability in the MCP explain_score output', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $run = ModelRun::factory()->create(['model_id' => MlModel::factory()->create(['model_name' => 'xgboost-fraud']), 'version_number' => 'v2026.10.1']);
    $alert->riskScore->update(['model_run_id' => $run->id, 'shadow_probability' => 0.25]);
    Sanctum::actingAs($analyst, ['mcp:read']);

    FraudInvestigationServer::tool(ExplainScoreTool::class, ['alert_id' => $alert->id])
        ->assertHasNoErrors()
        ->assertSee(['Model used: xgboost-fraud v2026.10.1', 'النموذج المستخدم: xgboost-fraud v2026.10.1', 'Shadow model probability: 0.25']);
});

it('says rules only in the MCP explain_score output without a model run', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    Sanctum::actingAs($analyst, ['mcp:read']);

    FraudInvestigationServer::tool(ExplainScoreTool::class, ['alert_id' => $alert->id])
        ->assertSee(['Model used: rules only', 'النموذج المستخدم: القواعد فقط']);
});

<?php

use App\Models\InvestigationCase;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\Investigation\CaseService;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/../Analyst/helpers.php';
require_once __DIR__.'/../Customer/Helpers.php';

/**
 * @return array<int, string> GET route names without required parameters whose name starts with one of the prefixes.
 */
function smokeRouteNames(array $prefixes, array $exact = []): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => in_array('GET', $route->methods(), true)
            && $route->getName() !== null
            && ! str_contains($route->uri(), '{')
            && (in_array($route->getName(), $exact, true) || collect($prefixes)->contains(fn ($p) => str_starts_with($route->getName(), $p))))
        ->map(fn (LaravelRoute $route) => $route->getName())
        ->values()
        ->all();
}

function assertPagesRender($test, User $user, array $names): void
{
    expect($names)->not->toBeEmpty();

    foreach ($names as $name) {
        $response = $test->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get(route($name));
        expect($response->status())->toBe(200, "Route [{$name}] returned {$response->status()}");
        preg_match('/\b(?:common|admin|analyst|supervisor|portal|auth)\.[a-z_]+\.[a-z_.]+\b(?![^<]*>)/u', $response->getContent(), $raw);
        expect($raw)->toBe([], "Route [{$name}] shows raw key ".($raw[0] ?? ''));
    }
}

it('renders every admin page as admin', function () {
    assertPagesRender($this, User::factory()->admin()->create(), smokeRouteNames(['admin.'], ['profile.security', 'docs.api']));
});

it('renders every supervisor page as supervisor', function () {
    assertPagesRender($this, User::factory()->supervisor()->create(), array_diff(
        smokeRouteNames(['supervisor.', 'analyst.'], ['profile.security', 'docs.api']),
        ['analyst.rule-suggestions'], // analysts only (rule_suggestions.create); supervisors review via supervisor.rule-suggestions
    ));
});

it('renders every analyst page as analyst', function () {
    assertPagesRender($this, User::factory()->analyst()->create(), smokeRouteNames(['analyst.'], ['profile.security', 'docs.api']));
});

it('renders every portal page as customer', function () {
    portalSeed();
    $c = portalCustomer();

    assertPagesRender($this, $c['user'], smokeRouteNames(['portal.'], ['profile.security', 'docs.api']));
});

it('renders parameterised analyst pages and the otp page', function () {
    portalSeed();
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert(['assigned_to' => $analyst->id]);
    $account = $alert->riskScore->transaction->account;
    $case = app(CaseService::class)->openFromAlert($alert, $analyst);

    foreach ([
        route('analyst.alerts.show', $alert),
        route('analyst.customers.show', $account->customer),
        route('analyst.accounts.network', $account),
        route('analyst.cases.show', $case),
    ] as $url) {
        $this->actingAs($analyst)->get($url)->assertOk();
    }

    expect(InvestigationCase::count())->toBe(1);
    $this->actingAs($analyst)->get(route('analyst.cases.pdf', $case))->assertOk();

    $c = portalCustomer();
    $challenge = OtpChallenge::factory()->create(['customer_id' => $c['customer']->id]);
    $this->actingAs($c['user'])->get(route('portal.otp', $challenge))->assertOk();
});

it('redirects the entry routes', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertRedirect();
});

it('keeps customers out of staff areas and staff out of the portal', function () {
    portalSeed();
    $customer = portalCustomer()['user'];

    foreach (smokeRouteNames(['admin.', 'supervisor.', 'analyst.']) as $name) {
        $this->actingAs($customer)->get(route($name))->assertForbidden();
    }

    foreach ([User::factory()->admin()->create(), User::factory()->supervisor()->create(), User::factory()->analyst()->create()] as $staff) {
        foreach (smokeRouteNames(['portal.']) as $name) {
            $this->actingAs($staff)->get(route($name))->assertForbidden();
        }
    }
});

it('keeps analysts out of admin and supervisor areas', function () {
    $analyst = User::factory()->analyst()->create();

    foreach (smokeRouteNames(['admin.', 'supervisor.']) as $name) {
        $this->actingAs($analyst)->get(route($name))->assertForbidden();
    }
});

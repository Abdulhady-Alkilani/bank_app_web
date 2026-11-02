<?php

use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\Device;
use App\Models\RiskScore;
use App\Models\Transaction;
use App\Services\Graph\GraphService;

/**
 * A --device--> B --beneficiary--> C --ip--> D
 */
function buildChain(): array
{
    [$a, $b, $c, $d] = Account::factory(4)->create()->all();
    $device = Device::factory()->create();

    Transaction::factory()->create(['account_id' => $a->id, 'device_id' => $device->id, 'ip_address' => '1.1.1.1']);
    $txB = Transaction::factory()->create(['account_id' => $b->id, 'device_id' => $device->id, 'ip_address' => '2.2.2.2']);
    Beneficiary::factory()->create(['account_id' => $b->id, 'beneficiary_account' => $c->account_number]);
    Transaction::factory()->create(['account_id' => $c->id, 'ip_address' => '3.3.3.3']);
    Transaction::factory()->create(['account_id' => $d->id, 'ip_address' => '3.3.3.3']);
    RiskScore::factory()->create(['transaction_id' => $txB->id, 'score' => 88]);

    return [$a, $b, $c, $d, $device];
}

function accountLabels(array $graph): array
{
    return collect($graph['nodes'])->where('data.type', 'account')->pluck('data.id')->all();
}

it('limits the network by depth', function () {
    [$a, $b, $c, $d] = buildChain();
    $graph = app(GraphService::class);

    $depth1 = accountLabels($graph->networkForAccount($a, 1));
    expect($depth1)->toBe(["account:{$a->id}"]);

    $depth2 = accountLabels($graph->networkForAccount($a, 2));
    expect($depth2)->toContain("account:{$b->id}")->not->toContain("account:{$c->id}");

    $depth3 = accountLabels($graph->networkForAccount($a, 3));
    expect($depth3)->toContain("account:{$a->id}", "account:{$b->id}", "account:{$c->id}")
        ->not->toContain("account:{$d->id}");

    $depth5 = accountLabels($graph->networkForAccount($a, 5));
    expect($depth5)->toContain("account:{$d->id}");
});

it('emits cytoscape-ready elements with risk', function () {
    [$a, $b] = buildChain();
    $network = app(GraphService::class)->networkForAccount($a, 3);

    $node = collect($network['nodes'])->firstWhere('data.id', "account:{$b->id}");
    expect($node['data'])->toHaveKeys(['id', 'label', 'type', 'risk'])
        ->and($node['data']['risk'])->toBe(88)
        ->and($network['edges'][0]['data'])->toHaveKeys(['id', 'source', 'target', 'label']);

    $ids = collect($network['nodes'])->pluck('data.id');
    foreach ($network['edges'] as $edge) {
        expect($ids)->toContain($edge['data']['source'])->and($ids)->toContain($edge['data']['target']);
    }
});

it('finds shared devices and related accounts', function () {
    [$a, $b, $c, $d, $device] = buildChain();
    $service = app(GraphService::class);

    $shared = $service->sharedDevices($a);
    expect($shared)->toHaveCount(1)
        ->and($shared->first()['device']->is($device))->toBeTrue()
        ->and($shared->first()['accounts']->pluck('id')->all())->toBe([$b->id]);

    $related = $service->relatedAccounts($a, 3)->pluck('id')->all();
    expect($related)->toContain($b->id, $c->id)->not->toContain($a->id, $d->id);
});

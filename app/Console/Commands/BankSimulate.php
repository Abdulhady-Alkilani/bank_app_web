<?php

namespace App\Console\Commands;

use App\Services\Customer\SecurityNotifier;
use App\Services\Simulation\AhmedScenario;
use App\Services\Simulation\BulkScenario;
use App\Services\Simulation\SimulationReset;
use App\Services\Simulation\SimulationScorer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('bank:simulate
    {--scenario=ahmed : ahmed | normal | mule | shared-device}
    {--via=pipeline : pipeline (direct service call) | http (signed POST /api/v1/score against APP_URL)}
    {--reset : Remove the previous data of the scenario first (idempotency keys prefixed sim-<scenario>-) and restore Ahmed\'s account}
    {--count=500 : Number of events for bulk scenarios}
    {--fraud-rate=0.02 : Share of fraud events in bulk scenarios}
    {--days=30 : Spread bulk events over the last N days}')]
#[Description('Bank simulator: replays the Ahmed account-takeover story or generates bulk traffic through the scoring pipeline')]
class BankSimulate extends Command
{
    public function handle(SimulationReset $reset): int
    {
        $scenario = (string) $this->option('scenario');
        $via = (string) $this->option('via');

        if (! in_array($scenario, ['ahmed', ...BulkScenario::SCENARIOS], true)) {
            $this->error("Unknown scenario [{$scenario}]. Use ahmed, normal, mule or shared-device.");

            return self::INVALID;
        }

        if (! in_array($via, [SimulationScorer::PIPELINE, SimulationScorer::HTTP], true)) {
            $this->error("Unknown --via [{$via}]. Use pipeline or http.");

            return self::INVALID;
        }

        // Run jobs (alerts, webhooks) inline so a simulation is complete when the command ends.
        if ($via === SimulationScorer::PIPELINE) {
            config(['queue.default' => 'sync']);
        }
        config(['mail.default' => 'array']);

        if ($this->option('reset')) {
            $this->components->task('Resetting previous '.$scenario.' simulation data', fn () => $reset->reset($scenario));
        }

        try {
            return $scenario === 'ahmed' ? $this->ahmed($via) : $this->bulk($scenario, $via);
        } catch (Throwable $e) {
            $this->error($e::class.': '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function ahmed(string $via): int
    {
        $this->info('Scenario: Ahmed account takeover (via '.$via.')');
        if ($via === SimulationScorer::HTTP) {
            $this->line('Calling '.rtrim((string) config('app.url'), '/').'/api/v1/score (alerts need a running queue worker).');
        }

        $outcome = app(AhmedScenario::class, ['scorer' => new SimulationScorer($via)])->run();

        $this->table(
            ['Time', 'Event', 'Score', 'Decision', 'Top reasons'],
            collect($outcome['rows'])->map(fn (array $r) => [
                $r['time'], $r['event'], $r['score'], strtoupper($r['decision']), $r['reasons'],
            ])->all(),
        );

        $account = $outcome['account'];
        $last = collect($outcome['rows'])->firstWhere('type', 'transfer');
        $this->newLine();
        $this->line('Ahmed account '.$account->account_number.': <options=bold>'.strtoupper($account->status->value).'</>');
        $this->line('Takeover transfer decision: '.strtoupper((string) ($last['decision'] ?? '-')).' (score '.($last['score'] ?? '-').')');
        $this->line('Alerts: '.$outcome['alerts'].' | Open cases: '.$outcome['cases']);

        return self::SUCCESS;
    }

    private function bulk(string $scenario, string $via): int
    {
        $count = max(1, (int) $this->option('count'));
        $rate = max(0.0, min(1.0, (float) $this->option('fraud-rate')));
        $days = max(1, (int) $this->option('days'));

        $this->info("Scenario: {$scenario} - {$count} events, fraud rate {$rate}, last {$days} days (via {$via})");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        SecurityNotifier::mute();
        try {
            $stats = app(BulkScenario::class, ['scorer' => new SimulationScorer($via)])
                ->run($scenario, $count, $rate, $days, function (int $done, int $total) use ($bar) {
                    $bar->setMaxSteps($total);
                    $bar->setProgress($done);
                });
        } finally {
            SecurityNotifier::mute(false);
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Value'], [
            ['Events scored', $stats['events']],
            ['Fraud events', $stats['fraud_events']],
            ['Fraud events flagged (challenge/block)', $stats['fraud_caught']],
            ['Normal events flagged (false positives)', $stats['false_positives']],
            ['Allow / Challenge / Block', implode(' / ', $stats['decisions'])],
            ['Frozen accounts', $stats['frozen']],
        ]);

        return self::SUCCESS;
    }
}

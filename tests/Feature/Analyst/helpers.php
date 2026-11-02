<?php

use App\Enums\AlertStatus;
use App\Enums\Decision;
use App\Enums\Priority;
use App\Models\Account;
use App\Models\Alert;
use App\Models\RiskScore;
use App\Models\Transaction;

if (! function_exists('makeAnalystAlert')) {
    /**
     * Create an alert (with risk score and transaction) for the given account.
     *
     * @param  array<string, mixed>  $alert
     * @param  array<string, mixed>  $transaction
     */
    function makeAnalystAlert(array $alert = [], int $score = 80, array $transaction = [], ?Account $account = null): Alert
    {
        $account ??= Account::factory()->create();
        $tx = Transaction::factory()->create($transaction + ['account_id' => $account->id]);
        $risk = RiskScore::factory()->create([
            'transaction_id' => $tx->id,
            'score' => $score,
            'decision' => $score >= 75 ? Decision::Block : ($score >= 40 ? Decision::Challenge : Decision::Allow),
            'reasons' => [['code' => 'NEW_DEVICE', 'text' => 'New device used', 'weight' => 30, 'source' => 'rule']],
            'features' => ['amount' => 5000, 'is_new_device' => true],
        ]);

        return Alert::factory()->create($alert + [
            'risk_score_id' => $risk->id,
            'status' => AlertStatus::Open,
            'priority' => Priority::High,
        ]);
    }
}

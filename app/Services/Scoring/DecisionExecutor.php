<?php

namespace App\Services\Scoring;

use App\Data\IngestedEvent;
use App\Enums\AccountActionType;
use App\Enums\Decision;
use App\Enums\TransactionStatus;
use App\Jobs\CreateAlertForRiskScore;
use App\Jobs\SendBankWebhook;
use App\Models\OtpChallenge;
use App\Models\RiskScore;
use App\Services\Accounts\AccountActionService;
use App\Services\Otp\OtpService;

/**
 * Applies the decision. The synchronous part is limited to status changes, the OTP and the freeze;
 * alert creation/assignment/notifications and the bank webhook run in queued jobs.
 */
class DecisionExecutor
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AccountActionService $actions,
    ) {}

    public function execute(RiskScore $riskScore, IngestedEvent $ingested): ?OtpChallenge
    {
        $tx = $ingested->transaction();
        $account = $ingested->account;
        $challenge = null;

        $common = [
            'account_number' => $account->account_number,
            'risk_score_id' => $riskScore->id,
            'score' => $riskScore->score,
            'transaction_id' => $tx?->id,
            'event_id' => $ingested->event()?->id,
        ];

        switch ($riskScore->decision) {
            case Decision::Allow:
                $tx?->update(['status' => TransactionStatus::Completed]);
                break;

            case Decision::Challenge:
                $tx?->update(['status' => TransactionStatus::Held]);
                $challenge = $this->otp->issue($ingested->customer, $ingested->subject);
                CreateAlertForRiskScore::dispatch($riskScore->id);
                SendBankWebhook::dispatch('require_otp', $common + ['otp_challenge_id' => $challenge->id]);
                break;

            case Decision::Block:
                $tx?->update(['status' => TransactionStatus::Blocked]);
                if (! $account->isFrozen()) {
                    $this->actions->execute(AccountActionType::FreezeAccount, $account, [
                        'transaction' => $tx,
                        'reason' => 'Automatic freeze: risk score '.$riskScore->score,
                        'requested_by' => null,
                    ]);
                }
                CreateAlertForRiskScore::dispatch($riskScore->id);
                if ($tx) {
                    SendBankWebhook::dispatch('block', $common);
                }
                SendBankWebhook::dispatch('freeze', $common);
                break;
        }

        return $challenge;
    }
}

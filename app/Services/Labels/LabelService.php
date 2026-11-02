<?php

namespace App\Services\Labels;

use App\Enums\LabelSource;
use App\Models\AccountEvent;
use App\Models\Alert;
use App\Models\Label;
use App\Models\Transaction;
use App\Models\User;

class LabelService
{
    public function record(?Transaction $transaction, ?AccountEvent $event, ?Alert $alert, bool $isFraud, LabelSource $source, ?User $by = null): Label
    {
        return Label::create([
            'transaction_id' => $transaction?->getKey(),
            'event_id' => $event?->getKey(),
            'alert_id' => $alert?->getKey(),
            'is_fraud' => $isFraud,
            'source' => $source,
            'labeled_by' => ($by ?? auth()->user())?->getKey(),
        ]);
    }
}

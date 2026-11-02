<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AnalystDecision: string
{
    use HasLabel;

    case ConfirmedFraud = 'confirmed_fraud';
    case FalsePositive = 'false_positive';
    case Escalated = 'escalated';
    case InfoRequested = 'info_requested';
}

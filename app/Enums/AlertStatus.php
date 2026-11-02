<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AlertStatus: string
{
    use HasLabel;

    case Open = 'open';
    case InReview = 'in_review';
    case Escalated = 'escalated';
    case PendingInfo = 'pending_info';
    case Closed = 'closed';
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum CaseStatus: string
{
    use HasLabel;

    case Open = 'open';
    case Investigating = 'investigating';
    case PendingApproval = 'pending_approval';
    case Closed = 'closed';
}

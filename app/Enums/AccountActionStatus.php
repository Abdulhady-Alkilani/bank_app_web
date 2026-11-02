<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AccountActionStatus: string
{
    use HasLabel;

    case PendingApproval = 'pending_approval';
    case Executed = 'executed';
    case Rejected = 'rejected';
}

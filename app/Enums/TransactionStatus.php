<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TransactionStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Completed = 'completed';
    case Held = 'held';
    case Blocked = 'blocked';
    case Rejected = 'rejected';
}

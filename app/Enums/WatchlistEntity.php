<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum WatchlistEntity: string
{
    use HasLabel;

    case Device = 'device';
    case Account = 'account';
    case Ip = 'ip';
    case Beneficiary = 'beneficiary';
}

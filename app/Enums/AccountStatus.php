<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AccountStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';
}

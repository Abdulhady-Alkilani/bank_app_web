<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum WatchlistType: string
{
    use HasLabel;

    case Blacklist = 'blacklist';
    case Whitelist = 'whitelist';
}

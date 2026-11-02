<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum RuleSuggestionStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}

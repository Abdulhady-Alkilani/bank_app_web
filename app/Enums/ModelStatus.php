<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ModelStatus: string
{
    use HasLabel;

    case Champion = 'champion';
    case Shadow = 'shadow';
    case Candidate = 'candidate';
    case Archived = 'archived';
}

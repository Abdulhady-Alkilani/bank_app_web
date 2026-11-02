<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum CustomerReportStatus: string
{
    use HasLabel;

    case New = 'new';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
}

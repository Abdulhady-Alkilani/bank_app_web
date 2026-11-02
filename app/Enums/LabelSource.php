<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum LabelSource: string
{
    use HasLabel;

    case Analyst = 'analyst';
    case Customer = 'customer';
    case Investigation = 'investigation';
    case Bank = 'bank';
}

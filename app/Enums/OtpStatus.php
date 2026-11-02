<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum OtpStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
    case Expired = 'expired';
}

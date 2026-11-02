<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum EventType: string
{
    use HasLabel;

    case Login = 'login';
    case Transfer = 'transfer';
    case AddBeneficiary = 'add_beneficiary';
    case ChangePhone = 'change_phone';
    case RaiseLimit = 'raise_limit';
    case OpenAccount = 'open_account';
}

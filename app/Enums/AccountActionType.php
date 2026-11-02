<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AccountActionType: string
{
    use HasLabel;

    case BlockTransaction = 'block_transaction';
    case RequireOtp = 'require_otp';
    case FreezeAccount = 'freeze_account';
    case UnfreezeAccount = 'unfreeze_account';
    case FreezeBeneficiary = 'freeze_beneficiary';
}

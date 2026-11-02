<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ApprovalType: string
{
    use HasLabel;

    case FreezeAccount = 'freeze_account';
    case UnfreezeAccount = 'unfreeze_account';
    case CloseHighValueCase = 'close_high_value_case';
    case FreezeBeneficiary = 'freeze_beneficiary';
}

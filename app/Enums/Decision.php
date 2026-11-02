<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum Decision: string
{
    use HasLabel;

    case Allow = 'allow';
    case Challenge = 'challenge';
    case Block = 'block';

    public function color(): string
    {
        return match ($this) {
            self::Allow => 'bg-emerald-100 text-emerald-800',
            self::Challenge => 'bg-amber-100 text-amber-800',
            self::Block => 'bg-red-100 text-red-800',
        };
    }
}

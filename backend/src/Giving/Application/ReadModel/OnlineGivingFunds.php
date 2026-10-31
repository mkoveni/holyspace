<?php

declare(strict_types=1);

namespace App\Giving\Application\ReadModel;

interface OnlineGivingFunds
{
    public function funds(): array;
}

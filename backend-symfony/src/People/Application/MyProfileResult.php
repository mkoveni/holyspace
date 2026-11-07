<?php

declare(strict_types=1);

namespace App\People\Application;

use App\People\Application\Contract\Title;

final readonly class MyProfileResult
{
    /** @param list<Title> $titles the selectable titles; empty when the account has no verified person */
    public function __construct(public ?MyProfile $profile, public array $titles)
    {
    }
}

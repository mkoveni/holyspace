<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use App\Volunteering\Domain\ValidityWindow;

final readonly class CredentialView
{
    public function __construct(public string $typeId, public ValidityWindow $window, public string $id = '')
    {
    }
}

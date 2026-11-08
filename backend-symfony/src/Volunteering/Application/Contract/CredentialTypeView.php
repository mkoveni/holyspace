<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

final readonly class CredentialTypeView
{
    public function __construct(public string $id, public string $name, public string $code = '')
    {
    }
}

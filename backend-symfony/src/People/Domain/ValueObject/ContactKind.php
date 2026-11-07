<?php

declare(strict_types=1);

namespace App\People\Domain\ValueObject;

enum ContactKind: string
{
    case Email = 'EMAIL';
    case Phone = 'PHONE';
}

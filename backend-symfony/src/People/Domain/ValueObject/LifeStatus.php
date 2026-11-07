<?php

declare(strict_types=1);

namespace App\People\Domain\ValueObject;

enum LifeStatus: string
{
    case Living = 'LIVING';
    case Deceased = 'DECEASED';
    case Unknown = 'UNKNOWN';
}

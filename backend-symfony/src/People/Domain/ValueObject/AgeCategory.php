<?php

declare(strict_types=1);

namespace App\People\Domain\ValueObject;

enum AgeCategory: string
{
    case Child = 'CHILD';
    case Adult = 'ADULT';
    case Unknown = 'UNKNOWN';
}

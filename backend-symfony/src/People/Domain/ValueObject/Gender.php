<?php

declare(strict_types=1);

namespace App\People\Domain\ValueObject;

enum Gender: string
{
    case Male = 'MALE';
    case Female = 'FEMALE';
    case Unknown = 'UNKNOWN';
}

<?php

declare(strict_types=1);

namespace App\Forms\Domain;

enum IdentityMode: string
{
    case Anonymous = 'ANONYMOUS';
    case Identified = 'IDENTIFIED';
}

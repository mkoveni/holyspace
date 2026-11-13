<?php

declare(strict_types=1);

namespace App\Forms\Domain;

enum FormStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Closed = 'CLOSED';
}

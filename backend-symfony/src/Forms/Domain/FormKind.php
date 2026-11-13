<?php

declare(strict_types=1);

namespace App\Forms\Domain;

enum FormKind: string
{
    case DataCollection = 'DATA_COLLECTION';
    case Quiz = 'QUIZ';
}

<?php

declare(strict_types=1);

namespace App\People\Application\ReadModel;

interface PeopleReportDirectory
{
    public function all(): array;
}

<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetFamily;

use App\People\Application\ReadModel\PeopleReadRepository;

final readonly class GetFamilyHandler
{
    public function __construct(private PeopleReadRepository $read) {}

    public function __invoke(GetFamilyQuery $q): array
    {
        $f = $this->read->family($q->id);

        if ($f === null) throw new \RuntimeException('Family not found.');

        return $f;
    }
}

<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPerson;

use App\People\Application\DTO\PersonView;
use App\People\Application\ReadModel\PeopleReadRepository;
use RuntimeException;

final readonly class GetPersonHandler
{
    public function __construct(private PeopleReadRepository $read)
    {
    }

    public function __invoke(GetPersonQuery $q): PersonView
    {
        $r = $this->read->person($q->id);
        if (null === $r) {
            throw new RuntimeException('Person not found.');
        }

        return new PersonView($r['id'], $r['first_name'], $r['last_name'], $r['date_of_birth'], $r['gender'], $r['email'], $r['phone'], $r['status'], $r['created_at'], $r['updated_at']);
    }
}

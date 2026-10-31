<?php

declare(strict_types=1);

namespace App\LifeEvents\Infrastructure\ReadModel;

use App\LifeEvents\Application\ReadModel\LifeEventReadStore;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Model\LifeEvent;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use App\LifeEvents\Domain\ValueObject\PersonId;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(LifeEventReadStore::class)]
final readonly class RepositoryLifeEventReadStore implements LifeEventReadStore
{
    public function __construct(private LifeEventRepository $records)
    {
    }

    public function findById(LifeEventId $id): ?LifeEvent
    {
        return $this->records->findById($id);
    }

    /** @return list<LifeEvent> */
    public function findForPerson(PersonId $personId): array
    {
        return $this->records->findForPerson($personId);
    }

    /** @return list<LifeEvent> */
    public function findByType(LifeEventType $type): array
    {
        return $this->records->findByType($type);
    }

    /** @return list<LifeEvent> */
    public function findUpcoming(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->records->findUpcoming($from, $to);
    }

    /** @return list<LifeEvent> */
    public function findAll(): array
    {
        return $this->records->findAll();
    }

    /** @param list<PersonId> $personIds */
    public function existsForParticipantsTypeOnDate(array $personIds, LifeEventType $type, DateTimeImmutable $date, ?LifeEventId $ignoreId = null): bool
    {
        return $this->records->existsForParticipantsTypeOnDate($personIds, $type, $date, $ignoreId);
    }
}

<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Repository;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Model\LifeEvent;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use App\LifeEvents\Domain\ValueObject\PersonId;
use DateTimeImmutable;
interface LifeEventRepository
{
    public function save(LifeEvent $lifeEvent): void;
    public function delete(LifeEventId $id): void;
    public function findById(LifeEventId $id): ?LifeEvent;
    /** @return list<LifeEvent> */ public function findForPerson(PersonId $personId): array;
    /** @return list<LifeEvent> */ public function findByType(LifeEventType $type): array;
    /** @return list<LifeEvent> */ public function findUpcoming(DateTimeImmutable $from, DateTimeImmutable $to): array;
    /** @return list<LifeEvent> */ public function findAll(): array;
    /** @param list<PersonId> $personIds */ public function existsForParticipantsTypeOnDate(array $personIds, LifeEventType $type, DateTimeImmutable $date, ?LifeEventId $ignoreId=null): bool;
}

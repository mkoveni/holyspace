<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Command\UpdateLifeEvent;

use App\LifeEvents\Application\Port\PersonExistenceChecker;
use App\LifeEvents\Application\Service\ParticipantInput;
use App\LifeEvents\Domain\Details\EngagementDetails;
use App\LifeEvents\Domain\Details\GenericLifeEventDetails;
use App\LifeEvents\Domain\Details\MarriageDetails;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Exception\LifeEventAlreadyExists;
use App\LifeEvents\Domain\Exception\LifeEventNotFound;
use App\LifeEvents\Domain\Model\LifeEventParticipant;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateLifeEventHandler
{
    public function __construct(private LifeEventRepository $lifeEvents, private PersonExistenceChecker $people)
    {
    }

    public function __invoke(UpdateLifeEventCommand $command): void
    {
        $id = LifeEventId::fromString($command->lifeEventId);
        $event = $this->lifeEvents->findById($id);
        if (!$event) {
            throw LifeEventNotFound::withId($command->lifeEventId);
        }
        $type = LifeEventType::from($command->type);
        $participants = ParticipantInput::parse($command->participants, $type);
        foreach ($participants as $p) {
            if (!$this->people->exists($p->personId())) {
                throw new InvalidArgumentException('Person '.$p->personId()->toString().' does not exist.');
            }
        }
        $date = new DateTimeImmutable($command->eventDate);
        $ids = array_map(fn (LifeEventParticipant $p) => $p->personId(), $participants);
        if ($this->lifeEvents->existsForParticipantsTypeOnDate($ids, $type, $date, $id)) {
            throw LifeEventAlreadyExists::forParticipants($type->value, $date->format('Y-m-d'));
        }
        $details = match ($type) {
            LifeEventType::MARRIAGE => MarriageDetails::fromArray($command->details),
            LifeEventType::ENGAGEMENT => EngagementDetails::fromArray($command->details),
            default => new GenericLifeEventDetails($command->details),
        };
        $event->update($type, $participants, $date, $command->notes, $details);
        $this->lifeEvents->save($event);
    }
}

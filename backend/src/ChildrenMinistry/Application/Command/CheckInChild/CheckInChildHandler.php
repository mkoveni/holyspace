<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\CheckInChild;

use App\ChildrenMinistry\Domain\Model\ChildCheckIn;
use App\ChildrenMinistry\Domain\Model\MinistryClass;
use App\ChildrenMinistry\Domain\Model\MinistrySession;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use App\Volunteering\Application\ReadModel\StaffingDirectory;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CheckInChildHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private PersonDirectory $people,
        private OccurrenceDirectory $occurrences,
        private StaffingDirectory $staffing,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CheckInChildCommand $message): object
    {
        $data = $message->data;

        return $this->mutate(function () use ($data) {
            $session = $this->required('sessions', $data['sessionId']);
            $occurrence = $this->occurrences->get($session->occurrenceId);
            if ('scheduled' !== $session->status || !$occurrence || 'cancelled' === $occurrence['status']) {
                throw new DomainException('Session is closed to arrivals.');
            }
            $this->staffMember($session, $data['staffPersonId']);
            foreach ($session->staffPersonIds as $staff) {
                if (!$this->staffing->eligibleForOccurrence($staff, $session->occurrenceId)) {
                    throw new DomainException('Session staffing needs review.');
                }
            }
            $now = $this->clock->now();
            if ($now < Input::instant($occurrence['startsAt'])->modify('-30 minutes') || $now > Input::instant($occurrence['endsAt'])) {
                throw new DomainException('Check-in opens 30 minutes before the session and closes at its end.');
            }
            $class = $this->required('classes', $session->classId);
            $this->requireAge($data['childId'], $class, Input::instant($occurrence['startsAt']));
            $enrolled = false;
            foreach ($this->records->all('enrolments') as $enrolment) {
                if ($enrolment->classId === $session->classId && $enrolment->childId === $data['childId'] && $enrolment->active && $enrolment->enrolledOn <= $now->format('Y-m-d')) {
                    $enrolled = true;
                }
            }
            if (!$enrolled) {
                throw new DomainException('Active enrolment is required.');
            }
            $count = 0;
            foreach ($this->records->all('checkins') as $checkin) {
                if (null === $checkin->checkedOutAt) {
                    if ($checkin->childId === $data['childId']) {
                        throw new DomainException('Child already has an open check-in.');
                    }
                    if ($checkin->sessionId === $session->id) {
                        ++$count;
                    }
                }
            }
            if ($count >= $class->capacity) {
                throw new DomainException('Session is full.');
            }

            return $this->save('checkins', new ChildCheckIn($session->id, $data['childId'], $now->format(DATE_ATOM), $data['staffPersonId']));
        });
    }

    private function mutate(callable $operation): mixed
    {
        $this->outbox->drainAll();

        return $this->transactions->run($operation);
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function staffMember(MinistrySession $session, string $personId): void
    {
        $this->person($personId);
        if (!in_array($personId, $session->staffPersonIds, true)) {
            throw new DomainException('Staff member is not assigned to this session.');
        }
    }

    private function requireAge(string $id, MinistryClass $class, DateTimeImmutable $date): void
    {
        $person = $this->person($id);
        if (!$person['dateOfBirth']) {
            throw new DomainException('Record the child’s date of birth first.');
        }
        $birth = Input::date($person['dateOfBirth']);
        $age = $birth->diff($date)->y;
        if ($birth > $date || $age < $class->minAge || $age > $class->maxAge) {
            throw new DomainException('Child does not meet the class age requirements.');
        }
    }

    private function save(string $kind, object $record): object
    {
        $this->records->save($record);

        return $this->required($kind, $record->id);
    }

    private function person(string $id): array
    {
        return $this->people->get($id) ?? throw new InvalidArgumentException('Person not found.');
    }
}

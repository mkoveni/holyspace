<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\EnrolChild;

use App\ChildrenMinistry\Domain\Model\Enrolment;
use App\ChildrenMinistry\Domain\Model\MinistryClass;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EnrolChildHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(EnrolChildCommand $message): object
    {
        $data = $message->data;

        return $this->mutate(function () use ($data) {
            $class = $this->required('classes', $data['classId']);
            $this->requireAge($data['childId'], $class, Input::date($data['enrolledOn']));
            $active = array_filter($this->records->all('enrolments'), fn ($e) => $e->active && $e->classId === $class->id);
            if (count($active) >= $class->capacity) {
                throw new DomainException('Class is full.');
            }
            foreach ($active as $enrolment) {
                if ($enrolment->childId === $data['childId']) {
                    throw new DomainException('Child is already enrolled.');
                }
            }

            return $this->save('enrolments', new Enrolment(...$data));
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

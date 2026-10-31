<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\AuthorisePickup;

use App\ChildrenMinistry\Domain\Model\PickupAuthorisation;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class AuthorisePickupHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(AuthorisePickupCommand $message): object
    {
        $data = $message->data;

        return $this->mutate(function () use ($data) {
            $child = $this->person($data['childId']);
            $collector = $this->person($data['collectorId']);
            $today = $this->clock->now()->setTime(0, 0);
            if (!$child['dateOfBirth'] || Input::date($child['dateOfBirth']) > $today || Input::date($child['dateOfBirth'])->diff($today)->y >= 18) {
                throw new DomainException('Pickup authorisations require a child under 18.');
            }
            if ($collector['dateOfBirth'] && Input::date($collector['dateOfBirth'])->diff($today)->y < 18) {
                throw new DomainException('Collector must be an adult.');
            }
            if (Input::date($data['validUntil'])->format('Y-m-d') < $today->format('Y-m-d')) {
                throw new InvalidArgumentException('Authorisation has already expired.');
            }
            foreach ($this->records->all('authorisations') as $existing) {
                if ($existing->active && $existing->childId === $data['childId'] && $existing->collectorId === $data['collectorId']) {
                    throw new DomainException('Revoke the existing authorisation before issuing a replacement.');
                }
            }

            return $this->save('authorisations', new PickupAuthorisation(...$data));
        });
    }

    private function mutate(callable $operation): mixed
    {
        $this->outbox->drainAll();

        return $this->transactions->run($operation);
    }

    private function person(string $id): array
    {
        return $this->people->get($id) ?? throw new InvalidArgumentException('Person not found.');
    }

    private function save(string $kind, object $record): object
    {
        $this->records->save($record);

        return $this->required($kind, $record->id);
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}

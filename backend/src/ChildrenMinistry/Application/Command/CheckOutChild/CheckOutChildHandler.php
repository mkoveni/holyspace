<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\CheckOutChild;

use App\ChildrenMinistry\Domain\Model\MinistrySession;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CheckOutChildHandler
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
    public function __invoke(CheckOutChildCommand $message): object
    {
        $id = $message->id;
        $data = $message->data;

        return $this->mutate(function () use ($id, $data) {
            $checkin = $this->required('checkins', $id);
            $verifiedLabelVersion = null;
            foreach ($this->records->all('labels') as $label) {
                if ($label->checkInId === $id) {
                    if (($data['expectedVersion'] ?? null) !== $checkin->version) {
                        throw new DomainException('Check-in changed. Reload before collection.');
                    }
                    if (($data['identityVerified'] ?? false) !== true) {
                        throw new DomainException('Confirm the collecting adult’s identity before handover.');
                    }
                    $verifiedLabelVersion = $label->version;
                    if (!hash_equals($label->code, strtoupper(trim($data['pickupCode'] ?? '')))) {
                        throw new DomainException('Pickup label does not match.');
                    }
                }
            }
            $session = $this->required('sessions', $checkin->sessionId);
            $this->staffMember($session, $data['staffPersonId']);
            $this->person($data['collectorId']);
            $valid = false;
            foreach ($this->records->all('authorisations') as $authorisation) {
                if ($authorisation->childId === $checkin->childId && $authorisation->collectorId === $data['collectorId'] && $authorisation->active && $authorisation->validUntil >= $this->clock->now()->format('Y-m-d')) {
                    $valid = true;
                }
            }
            if (!$valid) {
                throw new DomainException('Collector is not currently authorised.');
            }

            return $this->save('checkins', $checkin->collect($data['collectorId'], $data['staffPersonId'], $this->clock->now()->format(DATE_ATOM), null !== $verifiedLabelVersion, $verifiedLabelVersion));
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

    private function person(string $id): array
    {
        return $this->people->get($id) ?? throw new InvalidArgumentException('Person not found.');
    }

    private function save(string $kind, object $record): object
    {
        $this->records->save($record);

        return $this->required($kind, $record->id);
    }
}

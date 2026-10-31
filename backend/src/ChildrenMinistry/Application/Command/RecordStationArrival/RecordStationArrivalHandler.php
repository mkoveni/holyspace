<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\RecordStationArrival;

use App\ChildrenMinistry\Application\Command\CheckInChild\CheckInChildCommand;
use App\ChildrenMinistry\Application\Command\CheckInChild\CheckInChildHandler;
use App\ChildrenMinistry\Domain\Model\CheckInLabel;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RecordStationArrivalHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private PersonDirectory $people,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
        private AuthenticatedAccount $tokens,
        private AccessControl $access,
        private CheckInChildHandler $checkInChildHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(RecordStationArrivalCommand $message): array
    {
        $sessionId = $message->sessionId;
        $childId = $message->childId;
        $this->outbox->drainAll();

        return $this->transactions->run(function () use ($sessionId, $childId) {
            $this->session($sessionId);
            $checkin = ($this->checkInChildHandler)(new CheckInChildCommand(['sessionId' => $sessionId, 'childId' => $childId, 'staffPersonId' => $this->operator()]));
            $label = new CheckInLabel($checkin->id, strtoupper(bin2hex(random_bytes(6))));
            $this->records->save($label);

            return $this->labelView($checkin, $this->required('labels', $label->id));
        });
    }

    private function session(string $id): object
    {
        $operator = $this->operator();
        $session = $this->required('sessions', $id);
        if (!in_array($operator, $session->staffPersonIds, true)) {
            throw new AccessDenied('This session is not assigned to you.');
        }

        return $session;
    }

    private function operator(): string
    {
        $user = $this->tokens->user();
        if (!$this->access->isGranted('children.station') || null === $user || 'active' !== $user->status()->value || !$user->personId()) {
            throw new AccessDenied('An active account linked to supervising staff is required.');
        }

        return $user->personId();
    }

    private function labelView(object $checkin, CheckInLabel $label): array
    {
        $session = $this->session($checkin->sessionId);

        return ['checkInId' => $checkin->id, 'childName' => $this->people->get($checkin->childId)['name'] ?? 'Unavailable', 'className' => $this->required('classes', $session->classId)->name, 'occurrence' => $this->occurrences->get($session->occurrenceId), 'code' => $label->code, 'labelVersion' => $label->version];
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}

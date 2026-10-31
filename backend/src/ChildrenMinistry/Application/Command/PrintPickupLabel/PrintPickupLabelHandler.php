<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\PrintPickupLabel;

use App\ChildrenMinistry\Domain\Model\CheckInLabel;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class PrintPickupLabelHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private PersonDirectory $people,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
        private AuthenticatedAccount $tokens,
        private AccessControl $access,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(PrintPickupLabelCommand $message): array
    {
        $id = $message->id;
        $expectedVersion = $message->expectedVersion;
        $replacementReason = $message->replacementReason;
        $this->outbox->drainAll();

        return $this->transactions->run(function () use ($id, $expectedVersion, $replacementReason) {
            $checkin = $this->required('checkins', $id);
            $this->session($checkin->sessionId);
            if (null !== $checkin->checkedOutAt) {
                throw new DomainException('Child has already been collected.');
            }
            $label = $this->label($id);
            if (null === $label) {
                // Labels may be issued for existing staff check-ins.
                if (0 !== $expectedVersion) {
                    throw new DomainException('Reload the current label.');
                }
                $label = new CheckInLabel($id, strtoupper(bin2hex(random_bytes(6))));
            } else {
                if ($label->version !== $expectedVersion) {
                    throw new DomainException('Label changed. Reload before printing.');
                }
                $label = '' === trim($replacementReason) ? $label->reprint() : $label->replace($replacementReason);
            }
            $this->records->save($label);

            return $this->labelView($checkin, $this->required('labels', $label->id));
        });
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
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

    private function label(string $id): ?CheckInLabel
    {
        foreach ($this->records->all('labels') as $label) {
            if ($label->checkInId === $id) {
                return $label;
            }
        }

        return null;
    }

    private function labelView(object $checkin, CheckInLabel $label): array
    {
        $session = $this->session($checkin->sessionId);

        return ['checkInId' => $checkin->id, 'childName' => $this->people->get($checkin->childId)['name'] ?? 'Unavailable', 'className' => $this->required('classes', $session->classId)->name, 'occurrence' => $this->occurrences->get($session->occurrenceId), 'code' => $label->code, 'labelVersion' => $label->version];
    }

    private function operator(): string
    {
        $user = $this->tokens->user();
        if (!$this->access->isGranted('children.station') || null === $user || 'active' !== $user->status()->value || !$user->personId()) {
            throw new AccessDenied('An active account linked to supervising staff is required.');
        }

        return $user->personId();
    }
}

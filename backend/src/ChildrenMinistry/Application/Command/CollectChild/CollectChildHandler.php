<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\CollectChild;

use App\ChildrenMinistry\Application\Command\CheckOutChild\CheckOutChildCommand;
use App\ChildrenMinistry\Application\Command\CheckOutChild\CheckOutChildHandler;
use App\ChildrenMinistry\Domain\Model\CheckInLabel;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CollectChildHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
        private AuthenticatedAccount $tokens,
        private AccessControl $access,
        private CheckOutChildHandler $checkOutChildHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CollectChildCommand $message): object
    {
        $id = $message->id;
        $data = $message->data;
        $this->outbox->drainAll();

        return $this->transactions->run(function () use ($id, $data) {
            $checkin = $this->required('checkins', $id);
            $this->session($checkin->sessionId);
            if (null === $this->label($id)) {
                throw new DomainException('Issue paired labels before station collection.');
            }

            return ($this->checkOutChildHandler)(new CheckOutChildCommand($id, $data + ['staffPersonId' => $this->operator()]));
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

    private function operator(): string
    {
        $user = $this->tokens->user();
        if (!$this->access->isGranted('children.station') || null === $user || 'active' !== $user->status()->value || !$user->personId()) {
            throw new AccessDenied('An active account linked to supervising staff is required.');
        }

        return $user->personId();
    }
}

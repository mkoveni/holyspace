<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Query\GetPickupLabel;

use App\ChildrenMinistry\Application\ReadModel\ChildrenMinistryReadStore;
use App\ChildrenMinistry\Domain\Model\CheckInLabel;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use OutOfBoundsException;

final readonly class GetPickupLabelHandler
{
    public function __construct(private ChildrenMinistryReadStore $records, private AuthenticatedAccount $tokens, private AccessControl $access)
    {
    }

    public function __invoke(GetPickupLabelQuery $message): array
    {
        $id = $message->id;
        $checkin = $this->required('checkins', $id);
        $this->session($checkin->sessionId);
        $label = $this->label($id);

        return ['version' => $label?->version ?? 0];
        // Codes are disclosed only through the audited print operation.
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

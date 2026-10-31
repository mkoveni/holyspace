<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;

final class VolunteerInvitation extends WorkflowRecord
{
    public function __construct(public readonly string $assignmentId, public readonly int $assignmentVersion, public readonly int $shiftVersion, public readonly string $personId, public readonly string $expiresAt, public readonly string $startsAt, public readonly string $endsAt, public readonly string $status = 'pending', public readonly ?string $respondedAt = null, string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($assignmentId);
        Input::id($personId);
        Input::instant($expiresAt);
        Input::interval($startsAt, $endsAt);
        if (!in_array($status, ['pending', 'accepted', 'declined', 'expired', 'withdrawn'], true)) {
            throw new InvalidArgumentException('Invalid invitation status.');
        }
    }

    public function respond(string $status, string $time): self
    {
        if ('pending' !== $this->status) {
            throw new DomainException('Invitation is no longer pending.');
        }

        return $this->changed(['status' => $status, 'respondedAt' => $time]);
    }
}

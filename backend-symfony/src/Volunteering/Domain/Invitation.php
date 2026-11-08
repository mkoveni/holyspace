<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

use DateTimeImmutable;

/**
 * A request for the volunteer to accept or decline an assignment. It binds the assignment version and the shift's
 * schedule revision it was issued for; once answered, expired or withdrawn it never changes again.
 */
final readonly class Invitation
{
    public const array OUTCOMES = ['ACCEPTED', 'DECLINED', 'EXPIRED', 'WITHDRAWN'];

    private function __construct(
        public string $id,
        public string $assignmentId,
        public int $assignmentVersion,
        public int $shiftVersion,
        public DateTimeImmutable $expiresAt,
        public string $status,
        public int $version,
        public ?DateTimeImmutable $respondedAt,
    ) {
    }

    public static function issue(string $id, string $assignmentId, int $assignmentVersion, int $shiftVersion, DateTimeImmutable $expiresAt): self
    {
        return new self($id, $assignmentId, $assignmentVersion, $shiftVersion, $expiresAt, 'PENDING', 0, null);
    }

    public static function reconstitute(string $id, string $assignmentId, int $assignmentVersion, int $shiftVersion, DateTimeImmutable $expiresAt, string $status, int $version, ?DateTimeImmutable $respondedAt): self
    {
        return new self($id, $assignmentId, $assignmentVersion, $shiftVersion, $expiresAt, $status, $version, $respondedAt);
    }

    public function respond(string $next, DateTimeImmutable $at): self
    {
        if (!$this->isPending() || !\in_array($next, self::OUTCOMES, true)) {
            throw VolunteerException::conflict('This invitation is no longer pending.');
        }

        return new self($this->id, $this->assignmentId, $this->assignmentVersion, $this->shiftVersion, $this->expiresAt, $next, $this->version + 1, $at);
    }

    public function isPending(): bool
    {
        return 'PENDING' === $this->status;
    }
}

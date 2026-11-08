<?php

declare(strict_types=1);

namespace App\Communities\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** A pastoral follow-up for a current member: open until completed with a reason, which keeps the history. */
final readonly class FollowUp
{
    public const string OPEN = 'OPEN';
    public const string DONE = 'DONE';

    private function __construct(
        public string $id,
        public string $communityId,
        public string $personId,
        public string $note,
        public CalendarDate $dueOn,
        public string $status,
        public int $version,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public static function open(string $id, string $communityId, string $personId, string $note, CalendarDate $dueOn, string $createdBy, DateTimeImmutable $now): self
    {
        return new self($id, $communityId, $personId, CommunityRules::text($note, 2000), $dueOn, self::OPEN, 0, $createdBy, $now);
    }

    public static function reconstitute(string $id, string $communityId, string $personId, string $note, CalendarDate $dueOn, string $status, int $version, string $createdBy, DateTimeImmutable $createdAt): self
    {
        return new self($id, $communityId, $personId, $note, $dueOn, $status, $version, $createdBy, $createdAt);
    }

    public function complete(): self
    {
        if (self::OPEN !== $this->status) {
            throw CommunityException::conflict('This follow-up is already complete.');
        }

        return new self($this->id, $this->communityId, $this->personId, $this->note, $this->dueOn, self::DONE, $this->version + 1, $this->createdBy, $this->createdAt);
    }
}

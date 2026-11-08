<?php

declare(strict_types=1);

namespace App\Communities\Domain;

use DateTimeImmutable;

/**
 * A group gathering linked to one Events occurrence. Its member roster is frozen once (from two hours before the
 * start); guests may then be added. It ends cancelled or closed, and attendance corrections stay possible afterwards.
 */
final readonly class GroupMeeting
{
    public const string OPEN = 'OPEN';
    public const string CANCELLED = 'CANCELLED';
    public const string CLOSED = 'CLOSED';

    private function __construct(
        public string $id,
        public string $communityId,
        public string $occurrenceId,
        public DateTimeImmutable $scheduledAt,
        public string $status,
        public bool $rosterFrozen,
        public int $version,
    ) {
    }

    public static function schedule(string $id, string $communityId, string $occurrenceId, DateTimeImmutable $scheduledAt): self
    {
        return new self($id, $communityId, $occurrenceId, $scheduledAt, self::OPEN, false, 0);
    }

    public static function reconstitute(string $id, string $communityId, string $occurrenceId, DateTimeImmutable $scheduledAt, string $status, bool $rosterFrozen, int $version): self
    {
        return new self($id, $communityId, $occurrenceId, $scheduledAt, $status, $rosterFrozen, $version);
    }

    public function isOpen(): bool
    {
        return self::OPEN === $this->status;
    }

    /** The member roster is now fixed; a roster that is already frozen stays as it is. */
    public function freeze(): self
    {
        return $this->rosterFrozen ? $this : $this->change($this->status, true);
    }

    /** A guest was added to the frozen roster; the version moves so a second screen must refresh. */
    public function guestAdded(): self
    {
        if (!$this->rosterFrozen) {
            throw CommunityException::conflict('Prepare the member roster before adding guests.');
        }

        return $this->change($this->status, true);
    }

    public function requireOpen(): void
    {
        if (!$this->isOpen()) {
            throw CommunityException::conflict('This meeting has already been closed or cancelled.');
        }
    }

    /**
     * @param bool $ended whether the linked occurrence has ended
     */
    public function finish(string $status, bool $ended): self
    {
        if (!in_array($status, [self::CANCELLED, self::CLOSED], true)) {
            throw CommunityException::invalid('Choose cancel or close.');
        }
        $this->requireOpen();
        if (self::CLOSED === $status && (!$ended || !$this->rosterFrozen)) {
            throw CommunityException::conflict('Prepare and review attendance, then close the meeting after it ends.');
        }

        return $this->change($status, $this->rosterFrozen);
    }

    private function change(string $status, bool $frozen): self
    {
        return new self($this->id, $this->communityId, $this->occurrenceId, $this->scheduledAt, $status, $frozen, $this->version + 1);
    }
}

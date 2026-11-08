<?php

declare(strict_types=1);

namespace App\Communities\Domain;

/**
 * One person on a meeting's frozen roster: a member as of the start time, or a guest who does not join the group.
 * Attendance starts UNRECORDED; every recorded change moves the version.
 */
final readonly class RosterEntry
{
    public const string UNRECORDED = 'UNRECORDED';
    public const array STATUSES = ['PRESENT', 'ABSENT', 'EXCUSED', self::UNRECORDED];

    private function __construct(
        public string $id,
        public string $meetingId,
        public string $personId,
        public ?string $membershipId,
        public bool $guest,
        public string $status,
        public int $version,
    ) {
    }

    public static function member(string $id, string $meetingId, Membership $membership): self
    {
        return new self($id, $meetingId, $membership->personId, $membership->id, false, self::UNRECORDED, 0);
    }

    public static function guest(string $id, string $meetingId, string $personId): self
    {
        return new self($id, $meetingId, $personId, null, true, self::UNRECORDED, 0);
    }

    public static function reconstitute(string $id, string $meetingId, string $personId, ?string $membershipId, bool $guest, string $status, int $version): self
    {
        return new self($id, $meetingId, $personId, $membershipId, $guest, $status, $version);
    }

    public static function requireStatus(?string $status): string
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw CommunityException::invalid('Choose an attendance status.');
        }

        return $status;
    }

    /** The first recording is the one that needs an open meeting; later ones are corrections. */
    public function isFirstRecording(): bool
    {
        return 0 === $this->version;
    }

    public function record(string $status): self
    {
        return new self($this->id, $this->meetingId, $this->personId, $this->membershipId, $this->guest, self::requireStatus($status), $this->version + 1);
    }
}

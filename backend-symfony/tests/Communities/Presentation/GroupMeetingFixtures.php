<?php

declare(strict_types=1);

namespace App\Tests\Communities\Presentation;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

/** Scheduling, preparing and marking group meetings through the API, as Java's `CommunityIntegrationTestSupport`. */
trait GroupMeetingFixtures
{
    private string $venue;

    private function createVenue(): void
    {
        $this->call('POST', '/api/events/venues', $this->admin, ['name' => 'Lounge', 'address' => '', 'exclusive' => false]);
        self::assertSame(201, $this->code(), $this->message());
        $this->venue = (string) $this->json()['id'];
    }

    /**
     * Java `groupMeeting`'s schedule: by default one gathering a quarter of an hour from now, for an hour, in UTC.
     *
     * @return array<string, mixed>
     */
    private function schedule(int $count = 1, string $frequency = 'ONCE', int $startsIn = 900): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $start = $now->setTimestamp(intdiv($now->getTimestamp() + $startsIn, 60) * 60);
        $end = $start->modify('+1 hour');

        return [
            'venueId' => $this->venue, 'timeZone' => 'UTC', 'frequency' => $frequency, 'interval' => 1, 'count' => $count,
            'firstDate' => $start->format('Y-m-d'), 'startsAt' => $start->format('H:i'), 'endsAt' => $end->format('H:i'),
            'endDayOffset' => $end->format('Y-m-d') === $start->format('Y-m-d') ? 0 : 1,
        ];
    }

    /** @param array<string, mixed>|null $schedule */
    private function preview(string $group, ?array $schedule = null, ?string $token = null): string
    {
        $this->call('POST', self::ROOT."/$group/meeting-preview", $token ?? $this->admin, ['schedule' => $schedule ?? $this->schedule()]);
        self::assertSame(200, $this->code(), $this->message());

        return (string) $this->json()['hash'];
    }

    /** @param array<string, mixed>|null $schedule */
    private function meeting(string $group, ?array $schedule = null, int $version = 0): string
    {
        $schedule ??= $this->schedule();
        $this->call('POST', self::ROOT."/$group/meetings", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => $version, 'schedule' => $schedule, 'reviewHash' => $this->preview($group, $schedule)]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function prepare(string $meeting, int $version, ?string $token = null): void
    {
        $this->call('POST', self::ROOT."/meetings/$meeting/prepare", $token ?? $this->admin, ['expectedVersion' => $version]);
    }

    private function noContent(string $why = ''): void
    {
        self::assertSame(204, $this->code(), $why.' '.$this->body());
    }

    private function rosterRow(string $meeting, string $person): string
    {
        return (string) $this->connection->fetchOne('SELECT id FROM community_roster WHERE meeting_id = ? AND person_id = ?', [$meeting, $person]);
    }

    private function mark(string $row, int $version, string $status, ?string $token = null): void
    {
        $this->call('PUT', self::ROOT."/roster/$row", $token ?? $this->admin, ['expectedVersion' => $version, 'status' => $status, 'reason' => 'Verified by group coordinator']);
    }

    private function occurrence(string $meeting): string
    {
        return (string) $this->connection->fetchOne('SELECT occurrence_id FROM community_meetings WHERE id = ?', [$meeting]);
    }
}

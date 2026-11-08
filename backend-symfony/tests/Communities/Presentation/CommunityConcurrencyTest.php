<?php

declare(strict_types=1);

namespace App\Tests\Communities\Presentation;

use App\Tests\Giving\Infrastructure\ParallelSession;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * Java's concurrent cases (`concurrentJoiningCannotDuplicateMembershipOrAudit` and attendance on one roster row) with two
 * real database sessions. This session runs a request and keeps its transaction open; a forked session boots its own
 * kernel on its own connection, sends the competing request, and must block on this session's lock (proved through
 * `pg_stat_activity`) before deciding against the committed result. That exercises the recheck each handler makes after
 * taking its locks (person, then community, then meeting), which a sequential retry would skip.
 */
final class CommunityConcurrencyTest extends CommunitiesApiTestCase
{
    use GroupMeetingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // A reboot would replace the connection that holds this session's open transaction.
        $this->client->disableReboot();
    }

    /**
     * Sends `$first` through this session's kernel with the transaction left open, sends `$second` from another session,
     * proves it waits for a lock, then commits and returns the second session's "status body".
     *
     * @param array{string, string, array<string, mixed>} $first
     * @param array{string, string, array<string, mixed>} $second
     *
     * @return array{int, string}
     */
    private function contend(array $first, array $second): array
    {
        $this->connection->beginTransaction();
        $this->call($first[0], $first[1], $this->admin, $first[2]);
        $won = $this->code();
        $competitor = ParallelSession::start($this->remote($second[0], $second[1], $second[2]));
        $waited = $competitor->awaitsLock($this->connection);
        $this->connection->commit();
        $outcome = $competitor->outcome();

        self::assertTrue($waited, 'the second session must wait for the first session\'s lock; it finished with '.$outcome);

        return [$won, $outcome];
    }

    /**
     * The request as a second session sends it: a fresh kernel whose services use the forked connection.
     *
     * @param array<string, mixed> $body
     *
     * @return callable(Connection): string
     */
    private function remote(string $method, string $uri, array $body): callable
    {
        $token = $this->admin;

        return static function (Connection $connection) use ($method, $uri, $body, $token): string {
            $kernel = self::createKernel();
            $kernel->boot();
            $kernel->getContainer()->set(Connection::class, $connection);
            $response = $kernel->handle(Request::create($uri, $method, server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ], content: json_encode($body, \JSON_THROW_ON_ERROR)));

            return $response->getStatusCode().' '.$response->getContent();
        };
    }

    /** @return array{string, string, array<string, mixed>} */
    private function joinRequest(string $community, string $person, ?string $request = null): array
    {
        return ['POST', self::ROOT."/$community/members", ['requestId' => $request ?? Uuid::v4()->toRfc4122(), 'personId' => $person]];
    }

    public function testConcurrentJoiningCannotDuplicateMembershipOrAudit(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Concurrent');

        [$won, $outcome] = $this->contend($this->joinRequest($group, $person), $this->joinRequest($group, $person));

        self::assertSame(201, $won);
        self::assertStringStartsWith('ok:409 ', $outcome);
        self::assertStringContainsString('This person is already a current member.', $outcome);
        self::assertSame(1, $this->rows('community_memberships'));
        self::assertSame(1, $this->rows('community_history', "action = 'MEMBER_JOINED'"));
    }

    public function testAConcurrentRetryOfOneJoinReplaysTheCommittedMembership(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Retry');
        $join = $this->joinRequest($group, $person, Uuid::v4()->toRfc4122());

        [$won, $outcome] = $this->contend($join, $join);
        $membership = (string) $this->connection->fetchOne('SELECT id FROM community_memberships');

        self::assertSame(201, $won);
        self::assertStringStartsWith('ok:201 ', $outcome);
        self::assertStringContainsString($membership, $outcome);
        self::assertSame(1, $this->rows('community_requests', "target_id = '$membership'"));
        self::assertSame(1, $this->rows('community_memberships'));
        self::assertSame(1, $this->rows('community_history', "action = 'MEMBER_JOINED'"));
    }

    public function testConcurrentAttendanceOnOneRosterRowKeepsOneRecording(): void
    {
        $this->createVenue();
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Attender');
        $this->join($group, $person);
        $meeting = $this->meeting($group);
        $this->prepare($meeting, 0);
        $this->noContent('prepare');
        $row = $this->rosterRow($meeting, $person);
        $mark = static fn (string $status): array => ['PUT', self::ROOT."/roster/$row", ['expectedVersion' => 0, 'status' => $status, 'reason' => 'Verified by group coordinator']];

        [$won, $outcome] = $this->contend($mark('PRESENT'), $mark('ABSENT'));

        self::assertSame(204, $won);
        self::assertStringStartsWith('ok:409 ', $outcome, 'the waiting correction sees the committed version');
        self::assertSame('PRESENT', $this->connection->fetchOne('SELECT status FROM community_roster WHERE id = ?', [$row]));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT version FROM community_roster WHERE id = ?', [$row]));
        self::assertSame(1, $this->rows('community_history', "action LIKE 'ATTENDANCE_%'"));
    }

    public function testAJoinWaitingOnRosterPreparationStaysOffTheFrozenRoster(): void
    {
        $this->createVenue();
        $group = $this->community('GROUP', 'Home');
        $this->join($group, $this->person('Expected'));
        $meeting = $this->meeting($group);
        $late = $this->person('Late');

        [$won, $outcome] = $this->contend(['POST', self::ROOT."/meetings/$meeting/prepare", ['expectedVersion' => 0]], $this->joinRequest($group, $late));

        self::assertSame(204, $won);
        self::assertStringStartsWith('ok:201 ', $outcome, 'the join proceeds once the roster is frozen');
        self::assertSame(1, $this->rows('community_roster'));
        self::assertSame('', (string) $this->rosterRow($meeting, $late));
    }
}

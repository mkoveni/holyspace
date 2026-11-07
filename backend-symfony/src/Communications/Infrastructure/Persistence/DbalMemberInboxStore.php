<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Persistence;

use App\Communications\Application\Contract\InboxReader;
use App\Communications\Application\Contract\InboxWriter;
use App\Communications\Application\Contract\MemberInbox;
use App\Communications\Application\Contract\MemberNotification;
use App\Communications\Application\Contract\MemberNotificationKind;
use App\Communications\Application\Contract\MemberNotificationPreference;
use App\Communications\Application\Contract\MemberNotifications;
use App\Communications\Application\Contract\PushWriter;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\MemberNotice;
use App\Shared\Application\IdGenerator;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Clock\ClockInterface;

/**
 * The member inbox over `communication_member_notifications` and `communication_member_preferences` (V29).
 * A notice published while its topic is turned off is stored hidden, so a retry of the same source cannot bring it back.
 */
final readonly class DbalMemberInboxStore implements InboxReader, InboxWriter, MemberNotifications
{
    private const string NOTIFICATIONS = 'communication_member_notifications';
    private const string PREFERENCES = 'communication_member_preferences';

    public function __construct(
        private Connection $connection,
        private IdGenerator $ids,
        private ClockInterface $clock,
        private PushWriter $push,
    ) {
    }

    public function publish(?string $accountId, MemberNotificationKind $kind, string $sourceKey): void
    {
        if (null === $accountId) {
            return;
        }
        if ('' === trim($sourceKey) || mb_strlen($sourceKey) > 200) {
            throw CommunicationException::invalid('A stable notification source is required.');
        }
        $notice = MemberNotice::from($kind->value);
        // fetchOne() cannot tell a disabled topic (false) from no row (false), so read the row.
        $preference = $this->preferenceRow($accountId, $notice->topic())->select('enabled')->fetchAssociative();
        $visible = false === $preference || (bool) $preference['enabled'];
        // ON CONFLICT is not expressible with the query builder: a retry of the same source is a no-op.
        $inserted = $this->connection->fetchOne(
            'INSERT INTO '.self::NOTIFICATIONS.' (id, account_id, kind, source_key, visible, created_at)
             VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (account_id, kind, source_key) DO NOTHING RETURNING id',
            [$this->ids->uuid(), $accountId, $notice->value, $sourceKey, $visible, Timestamps::toDatabase($this->clock->now())],
            [4 => ParameterType::BOOLEAN],
        );
        // Push follows its own opt-in, so a notice hidden from the inbox can still be pushed (as in Java); a retry is not.
        if (false !== $inserted) {
            $this->push->enqueue((string) $inserted, $accountId, $notice->topic());
        }
    }

    public function list(string $account, bool $unreadOnly, int $limit, int $offset): MemberInbox
    {
        $page = $this->visible($account)
            ->select('id', 'kind', 'created_at', 'read_at')
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults($limit + 1)
            ->setFirstResult($offset);
        if ($unreadOnly) {
            $page->andWhere('read_at IS NULL');
        }
        $rows = $page->fetchAllAssociative();
        $unread = (int) $this->visible($account)->select('count(*)')->andWhere('read_at IS NULL')->fetchOne();

        return new MemberInbox(array_map(self::view(...), array_slice($rows, 0, $limit)), $unread, count($rows) > $limit);
    }

    public function preferences(string $account): array
    {
        $stored = [];
        $rows = $this->connection->createQueryBuilder()
            ->select('topic', 'enabled', 'version')
            ->from(self::PREFERENCES)
            ->where('account_id = :account')
            ->setParameter('account', $account)
            ->fetchAllAssociative();
        foreach ($rows as $row) {
            $stored[(string) $row['topic']] = new MemberNotificationPreference((string) $row['topic'], (bool) $row['enabled'], (int) $row['version']);
        }

        return array_map(
            static fn (string $topic): MemberNotificationPreference => $stored[$topic] ?? new MemberNotificationPreference($topic, true, 0),
            MemberNotice::TOPICS,
        );
    }

    public function notification(string $account, string $id): ?MemberNotification
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'kind', 'created_at', 'read_at')
            ->from(self::NOTIFICATIONS)
            ->where('account_id = :account', 'id = :id')
            ->setParameter('account', $account)
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : self::view($row);
    }

    public function read(string $account, string $notification, bool $read, DateTimeImmutable $at): void
    {
        $update = $this->connection->createQueryBuilder()
            ->update(self::NOTIFICATIONS)
            ->where('id = :id', 'account_id = :account', 'visible')
            ->setParameter('id', $notification)
            ->setParameter('account', $account);
        if ($read) {
            $update->set('read_at', 'coalesce(read_at, CAST(:at AS TIMESTAMPTZ))')->setParameter('at', Timestamps::toDatabase($at));
        } else {
            $update->set('read_at', 'NULL');
        }
        if (0 === (int) $update->executeStatement()) {
            throw CommunicationException::missing('Notification');
        }
    }

    public function preference(string $account, string $topic, bool $enabled, int $expectedVersion): void
    {
        // ON CONFLICT is not expressible with the query builder: create the default row, then lock it.
        $this->connection->executeStatement(
            'INSERT INTO '.self::PREFERENCES.' (account_id, topic) VALUES (?, ?) ON CONFLICT DO NOTHING',
            [$account, $topic],
        );
        $version = (int) $this->preferenceRow($account, $topic)->select('version')->forUpdate()->fetchOne();
        if ($version !== $expectedVersion) {
            throw CommunicationException::conflict('Notification preferences changed. Refresh and try again.');
        }
        $this->connection->createQueryBuilder()
            ->update(self::PREFERENCES)
            ->set('enabled', ':enabled')
            ->set('version', ':version')
            ->where('account_id = :account', 'topic = :topic')
            ->setParameter('enabled', $enabled, ParameterType::BOOLEAN)
            ->setParameter('version', $version + 1, ParameterType::INTEGER)
            ->setParameter('account', $account)
            ->setParameter('topic', $topic)
            ->executeStatement();
    }

    private function visible(string $account): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from(self::NOTIFICATIONS)
            ->where('account_id = :account', 'visible')
            ->setParameter('account', $account);
    }

    private function preferenceRow(string $account, string $topic): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from(self::PREFERENCES)
            ->where('account_id = :account', 'topic = :topic')
            ->setParameter('account', $account)
            ->setParameter('topic', $topic);
    }

    /** @param array<string, mixed> $row */
    private static function view(array $row): MemberNotification
    {
        $notice = MemberNotice::from((string) $row['kind']);

        return new MemberNotification(
            (string) $row['id'],
            $notice->topic(),
            $notice->title(),
            $notice->message(),
            $notice->destination(),
            Timestamps::fromDatabase((string) $row['created_at']),
            null === $row['read_at'] ? null : Timestamps::fromDatabase((string) $row['read_at']),
        );
    }
}

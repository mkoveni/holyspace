<?php

declare(strict_types=1);

namespace App\Tests\Communications\Presentation;

use App\Communications\Application\Contract\MemberNotificationKind;
use App\Communications\Application\Contract\MemberNotifications;
use App\Communications\Domain\CommunicationException;
use App\Shared\Application\UnitOfWork;
use App\Tests\Support\IdentityApiTestCase;
use RuntimeException;

/** Port of Java `MemberInboxApiIntegrationTest` plus the `/api/member/notifications/{id}` resolve route. */
final class MemberInboxApiTest extends IdentityApiTestCase
{
    private const string ROOT = '/api/member/notifications';
    private const string PREFS = '/api/member/notification-preferences';

    private string $admin;
    private string $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
    }

    private function notices(): MemberNotifications
    {
        $notices = self::getContainer()->get(MemberNotifications::class);
        \assert($notices instanceof MemberNotifications);

        return $notices;
    }

    private function publish(string $account, string $key): void
    {
        $this->notices()->publish($account, MemberNotificationKind::VolunteerInvitation, $key);
    }

    private function firstId(): string
    {
        return (string) $this->json()['items'][0]['id'];
    }

    public function testPrivateAccountScopeReadStatePagingAndForeignIdentifiers(): void
    {
        $other = $this->account('other');
        $token = $this->login('other');
        $this->publish($this->adminId, 'invitation-a');
        $this->publish($this->adminId, 'invitation-b');
        $this->publish($other, 'private-c');

        $this->call('GET', self::ROOT);
        self::assertSame(401, $this->code());

        $this->call('GET', self::ROOT.'?limit=1', $this->admin);
        self::assertSame(200, $this->code());
        self::assertSame([2, true], [$this->json()['unreadCount'], $this->json()['hasMore']]);
        $body = (string) $this->client->getResponse()->getContent();
        foreach (['accountId', 'sourceKey', 'invitation-a', 'private-c'] as $secret) {
            self::assertStringNotContainsString($secret, $body);
        }
        self::assertSame(['VOLUNTEERING', 'You’re invited to serve', 'VOLUNTEERING', null], [
            $this->json()['items'][0]['topic'], $this->json()['items'][0]['title'], $this->json()['items'][0]['destination'], $this->json()['items'][0]['readAt'],
        ]);
        self::assertMatchesRegularExpression('/Z\z/', (string) $this->json()['items'][0]['createdAt']);
        $id = $this->firstId();

        $this->call('PUT', self::ROOT."/$id/read", $token, ['read' => true]);
        self::assertSame(404, $this->code());
        $this->call('PUT', self::ROOT."/$id/read", $this->admin, ['read' => true]);
        self::assertSame(204, $this->code());
        $firstRead = $this->connection->fetchOne('SELECT read_at FROM communication_member_notifications WHERE id = ?', [$id]);
        self::assertNotNull($firstRead);

        $this->call('PUT', self::ROOT."/$id/read", $this->admin, ['read' => true]);
        self::assertSame(204, $this->code());
        self::assertSame($firstRead, $this->connection->fetchOne('SELECT read_at FROM communication_member_notifications WHERE id = ?', [$id]));

        $this->call('GET', self::ROOT.'?unreadOnly=true', $this->admin);
        self::assertSame(1, $this->json()['unreadCount']);
        self::assertCount(1, $this->json()['items']);
        $this->call('PUT', self::ROOT."/$id/read", $this->admin, ['read' => false]);
        self::assertSame(204, $this->code());
        $this->call('GET', self::ROOT, $this->admin);
        self::assertSame(2, $this->json()['unreadCount']);

        foreach (['?limit=0', '?limit=101', '?offset=10001', '?offset=-1', '?limit=x'] as $query) {
            $this->call('GET', self::ROOT.$query, $this->admin);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('PUT', self::ROOT."/$id/read", $this->admin, []);
        self::assertSame(400, $this->code());
        $this->call('PUT', self::ROOT.'/not-a-uuid/read', $this->admin, ['read' => true]);
        self::assertSame(400, $this->code());
    }

    public function testResolvingANoticeIsOwnAccountOnlyAndDoesNotMarkItRead(): void
    {
        $this->account('other');
        $token = $this->login('other');
        $this->publish($this->adminId, 'invitation-a');
        $this->call('GET', self::ROOT, $this->admin);
        $id = $this->firstId();

        $this->call('GET', self::ROOT."/$id", $this->admin);
        self::assertSame(200, $this->code());
        self::assertSame([$id, 'VOLUNTEERING', null], [$this->json()['id'], $this->json()['topic'], $this->json()['readAt']]);
        self::assertSame(1, $this->rows('communication_member_notifications', 'read_at IS NULL'));

        $this->call('GET', self::ROOT."/$id", $token);
        self::assertSame(404, $this->code());
        self::assertSame('Notification was not found.', $this->message());
    }

    public function testPreferencesAreVersionedPrivateAndOnlyAffectTheFutureInbox(): void
    {
        $this->account('other');
        $token = $this->login('other');

        $this->call('GET', self::PREFS, $this->admin);
        self::assertSame([['topic' => 'VOLUNTEERING', 'enabled' => true, 'version' => 0], ['topic' => 'EVENTS', 'enabled' => true, 'version' => 0]], $this->json());

        $this->publish($this->adminId, 'old');
        $this->call('PUT', self::PREFS.'/VOLUNTEERING', $this->admin, ['enabled' => false, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code());
        $this->call('PUT', self::PREFS.'/VOLUNTEERING', $this->admin, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(409, $this->code());
        self::assertSame('Notification preferences changed. Refresh and try again.', $this->message());

        $this->publish($this->adminId, 'suppressed');
        $this->call('GET', self::ROOT, $this->admin);
        self::assertSame(1, $this->json()['unreadCount']);

        $this->call('GET', self::PREFS, $token);
        self::assertSame([true, 0], [$this->json()[0]['enabled'], $this->json()[0]['version']]);

        $this->call('PUT', self::PREFS.'/UNKNOWN', $this->admin, ['enabled' => false, 'expectedVersion' => 0]);
        self::assertSame(400, $this->code());
        self::assertSame('Choose a supported topic and current preference version.', $this->message());
        $this->call('PUT', self::PREFS.'/EVENTS', $this->admin, ['expectedVersion' => 0]);
        self::assertSame(400, $this->code());
        $this->call('PUT', self::PREFS.'/EVENTS', $this->admin, ['enabled' => true, 'expectedVersion' => -1]);
        self::assertSame(400, $this->code());

        $this->call('PUT', self::PREFS.'/VOLUNTEERING', $this->admin, ['enabled' => true, 'expectedVersion' => 1]);
        self::assertSame(204, $this->code());
        $this->publish($this->adminId, 'suppressed'); // A retry must not resurrect a previously suppressed notice.
        $this->publish($this->adminId, 'new');
        $this->call('GET', self::ROOT, $this->admin);
        self::assertSame(2, $this->json()['unreadCount']);
    }

    public function testRetriesDoNotDuplicateAndAnOriginatingRollbackRemovesTheNotice(): void
    {
        $this->publish($this->adminId, 'same');
        $this->publish($this->adminId, 'same');
        $tx = self::getContainer()->get(UnitOfWork::class);
        \assert($tx instanceof UnitOfWork);
        $rolledBack = false;
        try {
            $tx->execute(function (): void {
                $this->publish($this->adminId, 'rollback');
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $expected) {
            $rolledBack = 'rollback' === $expected->getMessage();
        }
        self::assertTrue($rolledBack);

        $this->call('GET', self::ROOT, $this->admin);
        self::assertSame(1, $this->json()['unreadCount']);
        self::assertSame(1, $this->rows('communication_member_notifications'));
    }

    public function testPublishingNeedsAStableSourceAndIgnoresAMissingAccount(): void
    {
        $this->notices()->publish(null, MemberNotificationKind::DutyChanged, 'key');
        self::assertSame(0, $this->rows('communication_member_notifications'));
        foreach (['', '   ', str_repeat('k', 201)] as $key) {
            try {
                $this->publish($this->adminId, $key);
                self::fail('A blank or oversized source key must be refused.');
            } catch (CommunicationException $expected) {
                self::assertSame('A stable notification source is required.', $expected->getMessage());
            }
        }
        $this->publish($this->adminId, str_repeat('é', 200));
        self::assertSame(1, $this->rows('communication_member_notifications'));
    }

    public function testReadsAreReadOnlyNeedNoPeopleBindingAndAreNeverCached(): void
    {
        $this->account('member');
        $token = $this->login('member');

        $this->call('GET', self::ROOT, $token);
        self::assertSame(200, $this->code());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->call('GET', self::PREFS, $token);
        self::assertSame(200, $this->code());
        self::assertSame(0, $this->rows('communication_member_notifications'));
        self::assertSame(0, $this->rows('communication_member_preferences'));
    }
}

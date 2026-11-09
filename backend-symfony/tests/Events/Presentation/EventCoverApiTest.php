<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use App\Tests\Media\Infrastructure\ImageFixtures;
use App\Tests\Support\CoverImageFixtures;
use DateTimeImmutable;

/** An event's picture is a Media `event` image: staff upload, `coverImageUrl` for members, the public route. */
final class EventCoverApiTest extends EventsApiTestCase
{
    use CoverImageFixtures;

    private string $event;
    private string $occurrence;
    private string $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->event($this->draft((new DateTimeImmutable('+2 days'))->format('Y-m-d'), 1));
        $this->occurrence = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences LIMIT 1');
        $this->call('PUT', "/api/events/{$this->event}/member-offer", $this->manager, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code());
        $this->accountWith('access', ['identity.manage', 'people.manage']);
        $access = $this->login('access');
        $account = $this->account('ada');
        $this->call('PUT', "/api/member-access/$account", $access, ['personId' => $this->person('Ada'), 'active' => true, 'expectedVersion' => 0, 'evidence' => 'Verified in person']);
        self::assertSame(204, $this->code());
        $this->member = $this->login('ada');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg('/tmp/onechurch-media-test'));
        parent::tearDown();
    }

    private function path(?string $event = null): string
    {
        return '/api/media/event/'.($event ?? $this->event);
    }

    private function memberCover(): ?string
    {
        $this->call('GET', "/api/member/events/{$this->occurrence}", $this->member);
        self::assertSame(200, $this->code());
        $url = $this->json()['coverImageUrl'];
        $this->call('GET', '/api/member/events', $this->member);
        self::assertSame($url, $this->json()['items'][0]['coverImageUrl'], 'the list and the detail agree');

        return null === $url ? null : (string) $url;
    }

    public function testWithoutACoverNothingChangesForMembers(): void
    {
        self::assertNull($this->memberCover());
        $this->call('GET', $this->path(), $this->reader);
        self::assertSame(['version' => 0, 'image' => null], $this->json());
        $this->call('GET', $this->path().'/content', $this->reader);
        self::assertSame(404, $this->code());
    }

    public function testStaffUploadReplaceAndRemoveRoundTripsToTheMemberApi(): void
    {
        $this->uploadCover($this->path(), $this->manager, 0, ImageFixtures::png(1600, 900));
        self::assertSame(200, $this->code(), (string) $this->client->getResponse()->getContent());
        $first = $this->json();
        self::assertSame(1, $first['version']);
        self::assertSame(['image/webp', 1280, 720], [$first['image']['mime'], $first['image']['width'], $first['image']['height']]);
        $url = $this->memberCover();
        self::assertSame($first['image']['url'], $url);
        self::assertMatchesRegularExpression('#\A/api/public/media/[0-9a-f-]{36}\z#', (string) $url, 'a same-origin path with a random id and no credentials');

        $this->fetchPublicCover((string) $url);
        $response = $this->client->getResponse();
        self::assertSame([200, 'image/webp', 'nosniff'], [$response->getStatusCode(), $response->headers->get('Content-Type'), $response->headers->get('X-Content-Type-Options')]);
        self::assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));

        $this->uploadCover($this->path(), $this->manager, 1, ImageFixtures::jpeg(), 'image/png');
        self::assertSame([200, 2], [$this->code(), $this->json()['version']]);
        self::assertNotSame($url, $this->memberCover());

        $this->removeCover($this->path(), $this->manager, 2);
        self::assertSame(['version' => 3, 'image' => null], $this->json());
        self::assertNull($this->memberCover());
        self::assertSame(3, $this->rows('media_audit'));
    }

    public function testPermissionsVersionsAndMalformedUploads(): void
    {
        $this->uploadCover($this->path(), null, 0, ImageFixtures::png());
        self::assertSame(401, $this->code());
        $this->uploadCover($this->path(), $this->reader, 0, ImageFixtures::png());
        self::assertSame(403, $this->code());
        $this->uploadCover($this->path(), $this->member, 0, ImageFixtures::png());
        self::assertSame(403, $this->code());
        $this->call('GET', $this->path(), $this->member);
        self::assertSame(403, $this->code());
        $this->call('GET', $this->path(), $this->reader);
        self::assertSame(200, $this->code());

        $this->uploadCover($this->path(), $this->manager, null, ImageFixtures::png());
        self::assertSame(400, $this->code(), 'the version header is required');
        $this->uploadCover($this->path(), $this->manager, 4, ImageFixtures::png());
        self::assertSame(409, $this->code());
        $this->uploadCover($this->path(), $this->manager, 0, '<svg onload="alert(1)"/>', 'image/png');
        self::assertSame(400, $this->code());
        $this->uploadCover($this->path('00000000-0000-4000-8000-000000000001'), $this->manager, 0, ImageFixtures::png());
        self::assertSame(404, $this->code());
        $this->uploadCover('/api/media/event/not-a-uuid', $this->manager, 0, ImageFixtures::png());
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('media_files'), 'failed commands store nothing');
    }
}

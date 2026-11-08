<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use App\Tests\Support\CoverImageBytes;
use App\Tests\Support\CoverImageFixtures;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/** Admin upload, member `coverImageUrl` and the public image route, end to end. */
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
        $this->uploadCover($this->path(), $this->manager, 0, CoverImageBytes::png(1200, 300));
        self::assertSame(200, $this->code(), (string) $this->client->getResponse()->getContent());
        $first = $this->json();
        self::assertSame(1, $first['version']);
        self::assertSame(['image/png', 1200, 300], [$first['image']['mime'], $first['image']['width'], $first['image']['height']]);
        $url = $this->memberCover();
        self::assertSame($first['image']['url'], $url);
        self::assertMatchesRegularExpression('#\A/api/public/cover-images/[0-9a-f-]{36}\z#', (string) $url, 'a same-origin path with a random id and no credentials');

        $this->fetchPublicCover((string) $url);
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(CoverImageBytes::png(1200, 300), $response->getContent());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));

        $this->call('GET', $this->path().'/content', $this->reader);
        self::assertSame(200, $this->code());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'), 'the staff preview is never cached');

        $this->uploadCover($this->path(), $this->manager, 1, CoverImageBytes::jpeg(), 'image/png');
        self::assertSame(200, $this->code());
        self::assertSame(['image/jpeg', 2], [$this->json()['image']['mime'], $this->json()['version']], 'the bytes decide the type, not the declared one');
        $replaced = $this->memberCover();
        self::assertNotSame($url, $replaced);
        $this->fetchPublicCover((string) $url);
        self::assertSame(404, $this->code(), 'replaced bytes are deleted');
        self::assertSame(1, $this->rows('cover_images'));

        $this->removeCover($this->path(), $this->manager, 2);
        self::assertSame(['version' => 3, 'image' => null], $this->json());
        self::assertNull($this->memberCover());
        $this->fetchPublicCover((string) $replaced);
        self::assertSame(404, $this->code());
        self::assertSame(0, $this->rows('cover_images'));
        self::assertSame(3, $this->rows('event_audit', "action = 'event.cover_image.changed'"));
    }

    public function testPermissionsVersionsAndMalformedUploads(): void
    {
        $this->uploadCover($this->path(), null, 0, CoverImageBytes::png());
        self::assertSame(401, $this->code());
        $this->uploadCover($this->path(), $this->reader, 0, CoverImageBytes::png());
        self::assertSame(403, $this->code());
        $this->uploadCover($this->path(), $this->member, 0, CoverImageBytes::png());
        self::assertSame(403, $this->code());
        $this->call('GET', $this->path(), $this->member);
        self::assertSame(403, $this->code());

        $this->uploadCover($this->path(), $this->manager, null, CoverImageBytes::png());
        self::assertSame(400, $this->code(), 'the version header is required');
        $this->uploadCover($this->path(), $this->manager, 4, CoverImageBytes::png());
        self::assertSame(409, $this->code());
        $this->uploadCover($this->path(), $this->manager, 0, '<svg onload="alert(1)"/>', 'image/png');
        self::assertSame([400, 'Choose a JPEG, PNG or WebP image.'], [$this->code(), $this->json()['message'] ?? null]);
        $this->client->request('POST', $this->path(), server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->manager, 'HTTP_X_IMAGE_VERSION' => '0']);
        self::assertSame(400, $this->code(), 'no file part');
        $this->uploadCover('/api/media/event/'.Uuid::v4()->toRfc4122(), $this->manager, 0, CoverImageBytes::png());
        self::assertSame(404, $this->code());
        $this->uploadCover('/api/media/event/not-a-uuid', $this->manager, 0, CoverImageBytes::png());
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('cover_images'), 'failed commands store nothing');
        self::assertSame(0, $this->rows('event_cover_images'));

        $this->fetchPublicCover('/api/public/cover-images/'.Uuid::v4()->toRfc4122());
        self::assertSame(404, $this->code());
        $this->fetchPublicCover('/api/public/cover-images/x');
        self::assertSame(400, $this->code());
    }
}

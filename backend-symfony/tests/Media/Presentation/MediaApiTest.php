<?php

declare(strict_types=1);

namespace App\Tests\Media\Presentation;

use App\Tests\Media\Infrastructure\ImageFixtures;
use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RouterInterface;

/**
 * The HTTP contract end to end with real adapters: permissions (the owning context decides), version handling,
 * upload validation, the personal/public split, response headers, the upload rate limit and CORS.
 */
final class MediaApiTest extends IdentityApiTestCase
{
    private const string PERSON = 'a0000000-0000-4000-8000-000000000001';
    private const string EVENT = 'e0000000-0000-4000-8000-000000000001';
    private const string HOUSEHOLD = 'b0000000-0000-4000-8000-000000000001';
    private const string COMMUNITY = 'c0000000-0000-4000-8000-000000000001';
    private const string MISSING = '00000000-0000-4000-8000-000000000001';

    private string $editor;
    private string $viewer;
    private string $nobody;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('editor', ['people.manage', 'events.manage']);
        $this->accountWith('viewer', ['people.read']);
        $this->account('nobody');
        $this->seedOwners();
        $this->editor = $this->login('editor');
        $this->viewer = $this->login('viewer');
        $this->nobody = $this->login('nobody');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg('/tmp/onechurch-media-test'));
        parent::tearDown();
    }

    private function seedOwners(): void
    {
        $now = '2026-01-01T00:00:00Z';
        $this->connection->executeStatement(
            "INSERT INTO people_persons (id, first_name, last_name, gender, created_at, updated_at) VALUES (?, 'Ada', 'Lovelace', 'UNKNOWN', ?, ?)",
            [self::PERSON, $now, $now],
        );
        $this->connection->executeStatement('INSERT INTO people_households (id, name, created_at, updated_at) VALUES (?, ?, ?, ?)', [self::HOUSEHOLD, 'Lovelace', $now, $now]);
        $this->connection->executeStatement("INSERT INTO event_definitions (id, title, type, active, version) VALUES (?, 'Conference', 'CONFERENCE', TRUE, 0)", [self::EVENT]);
        $this->connection->executeStatement("INSERT INTO communities (id, kind, name, description) VALUES (?, 'GROUP', 'Home group', '')", [self::COMMUNITY]);
    }

    public function testEveryRouteNeedsASessionExceptPublicPictures(): void
    {
        $path = '/api/media/person/'.self::PERSON;
        $this->call('GET', $path);
        self::assertSame(401, $this->code());
        $this->call('GET', $path.'/content');
        self::assertSame(401, $this->code());
        $this->upload($path, null, 0, ImageFixtures::png());
        self::assertSame(401, $this->code());
        $this->remove($path, null, 0);
        self::assertSame(401, $this->code());
    }

    public function testAnUnusedSlotIsVersionZeroWithNoImage(): void
    {
        $this->call('GET', '/api/media/person/'.self::PERSON, $this->viewer);

        self::assertSame(200, $this->code());
        self::assertSame(['version' => 0, 'image' => null], $this->json());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testUploadStoresAPersonalPictureServedOnlyToAuthorisedCallers(): void
    {
        $path = '/api/media/person/'.self::PERSON;

        $this->upload($path, $this->editor, 0, ImageFixtures::jpeg(400, 300));

        self::assertSame(200, $this->code(), $this->message());
        $body = $this->json();
        self::assertSame(1, $body['version']);
        self::assertSame(['image/webp', 250, 250, false], [$body['image']['mime'], $body['image']['width'], $body['image']['height'], $body['image']['public']]);
        self::assertSame($path.'/content', $body['image']['url']);
        self::assertSame([['width' => 64, 'url' => $path.'/content?variant=64']], $body['image']['variants']);
        self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T/', $body['image']['updatedAt']);
        self::assertStringNotContainsString('.webp', (string) $this->client->getResponse()->getContent(), 'no storage path in the response');

        $this->call('GET', $path.'/content', $this->viewer);
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/webp', $response->headers->get('Content-Type'));
        self::assertSame('RIFF', substr((string) $response->getContent(), 0, 4));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame("default-src 'none'; sandbox", $response->headers->get('Content-Security-Policy'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        $this->call('GET', $path.'/content?variant=64', $this->viewer);
        self::assertSame([64, 64], array_slice(getimagesizefromstring((string) $this->client->getResponse()->getContent()) ?: [], 0, 2));
    }

    public function testVariantsMustBeOnesTheKindKeeps(): void
    {
        $path = '/api/media/person/'.self::PERSON;
        $this->upload($path, $this->editor, 0, ImageFixtures::png());

        $this->call('GET', $path.'/content?variant=999', $this->viewer);
        self::assertSame(404, $this->code());
        foreach (['abc', '0', '-5', '640000'] as $bad) {
            $this->call('GET', $path.'/content?variant='.$bad, $this->viewer);
            self::assertSame(400, $this->code(), $bad);
        }
    }

    public function testThePermissionsOfTheOwningContextDecide(): void
    {
        $path = '/api/media/person/'.self::PERSON;
        $this->upload($path, $this->viewer, 0, ImageFixtures::png());
        self::assertSame(403, $this->code(), 'viewing is not managing');
        $this->remove($path, $this->viewer, 0);
        self::assertSame(403, $this->code());
        $this->call('GET', $path, $this->nobody);
        self::assertSame(403, $this->code());
        $this->call('GET', $path.'/content', $this->nobody);
        self::assertSame(403, $this->code());
        self::assertSame(0, $this->rows('media_files'));
        self::assertSame(0, $this->rows('media_audit'));
    }

    public function testAMissingOwnerIsA404OnlyForThoseAllowedToKnow(): void
    {
        $path = '/api/media/person/'.self::MISSING;
        $this->upload($path, $this->editor, 0, ImageFixtures::png());
        self::assertSame(404, $this->code());
        $this->call('GET', $path, $this->viewer);
        self::assertSame(404, $this->code());
        $this->call('GET', $path, $this->nobody);
        self::assertSame(403, $this->code(), 'no access, so no hint about existence');
    }

    public function testEveryOwnerTypeIsHandledByItsOwnContext(): void
    {
        $this->accountWith('pastor', ['ministries.manage', 'ministries.read']);
        $pastor = $this->login('pastor');
        $this->accountWith('lead', ['ministries.lead']);
        $lead = $this->login('lead');

        $cases = [
            ['family', self::HOUSEHOLD, $this->editor, $this->viewer, 1200, 800],
            ['family_banner', self::HOUSEHOLD, $this->editor, $this->viewer, 1200, 300],
            ['event', self::EVENT, $this->editor, $this->editor, 1280, 720],
            ['community', self::COMMUNITY, $pastor, $lead, 1280, 720],
        ];
        foreach ($cases as [$kind, $owner, $writer, $reader, $width, $height]) {
            $path = '/api/media/'.$kind.'/'.$owner;
            $this->upload($path, $writer, 0, ImageFixtures::png(1600, 900));
            self::assertSame(200, $this->code(), $kind.' '.$this->message());
            self::assertSame([$width, $height], [$this->json()['image']['width'], $this->json()['image']['height']], $kind);
            $this->call('GET', $path, $reader);
            self::assertSame(200, $this->code(), $kind.' is visible to its readers');
            $this->upload($path, $this->nobody, 1, ImageFixtures::png());
            self::assertSame(403, $this->code(), $kind);
            $this->upload('/api/media/'.$kind.'/'.self::MISSING, $writer, 0, ImageFixtures::png());
            self::assertSame(404, $this->code(), $kind);
        }
        $this->upload('/api/media/community/'.self::COMMUNITY, $lead, 1, ImageFixtures::png());
        self::assertSame(403, $this->code(), 'a leader may look but not change a community picture');
    }

    public function testAnAccountOwnsItsProfilePictureAndAdministratorsMayChangeIt(): void
    {
        $this->accountWith('admin', ['identity.manage']);
        $admin = $this->login('admin');
        $mine = (string) $this->connection->fetchOne("SELECT id FROM user_accounts WHERE username = 'nobody'");
        $theirs = (string) $this->connection->fetchOne("SELECT id FROM user_accounts WHERE username = 'viewer'");
        $path = '/api/media/profile/'.$mine;

        $this->upload($path, $this->nobody, 0, ImageFixtures::png());
        self::assertSame([200, false, 250], [$this->code(), $this->json()['image']['public'], $this->json()['image']['width']]);
        $this->call('GET', $path.'/content', $this->nobody);
        self::assertSame(200, $this->code());
        $this->call('GET', '/api/media/profile/'.$theirs, $this->nobody);
        self::assertSame(403, $this->code(), 'not someone else\'s picture');
        $this->upload($path, $admin, 1, ImageFixtures::png());
        self::assertSame(200, $this->code());
    }

    public function testMalformedPathsAndUnknownTargetsAreRejected(): void
    {
        $this->call('GET', '/api/media/person/not-a-uuid', $this->editor);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/media/unknown/'.self::PERSON, $this->editor);
        self::assertSame(404, $this->code());
    }

    public function testVersionsAreRequiredAndCheckedAndAStaleUploadStoresNothing(): void
    {
        $path = '/api/media/person/'.self::PERSON;
        $this->upload($path, $this->editor, null, ImageFixtures::png());
        self::assertSame(400, $this->code());
        foreach (['abc', '-1', '01', '1.5', ''] as $bad) {
            $this->upload($path, $this->editor, $bad, ImageFixtures::png());
            self::assertSame(400, $this->code(), 'version "'.$bad.'"');
        }
        $this->upload($path, $this->editor, 5, ImageFixtures::png());
        self::assertSame(409, $this->code(), 'a never-used slot is version 0');
        self::assertSame(0, $this->rows('media_files'));

        $this->upload($path, $this->editor, 0, ImageFixtures::png());
        self::assertSame(200, $this->code());
        $this->upload($path, $this->editor, 0, ImageFixtures::png());
        self::assertSame(409, $this->code());
        self::assertSame(1, $this->rows('media_files'));
        $this->remove($path, $this->editor, 0);
        self::assertSame(409, $this->code());
    }

    public function testInvalidUploadsAreA400AndChangeNothing(): void
    {
        $path = '/api/media/person/'.self::PERSON;
        $this->client->request('POST', $path, server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->editor, 'HTTP_X_IMAGE_VERSION' => '0']);
        self::assertSame(400, $this->code(), 'no image part');
        foreach (['plain text', ImageFixtures::gif(), str_repeat('x', 5 * 1024 * 1024 + 1), substr(ImageFixtures::jpeg(400, 300), 0, 300)] as $bytes) {
            $this->upload($path, $this->editor, 0, $bytes);
            self::assertSame(400, $this->code(), 'declared as a PNG but judged by its bytes');
        }
        self::assertSame(0, $this->rows('media_files'));
        self::assertSame(0, $this->rows('media_slots'));
    }

    public function testReplacingAndRemovingAdvanceTheVersionAndQueueTheOldFile(): void
    {
        $path = '/api/media/person/'.self::PERSON;
        $this->upload($path, $this->editor, 0, ImageFixtures::png());
        $this->upload($path, $this->editor, 1, ImageFixtures::jpeg());
        self::assertSame(2, $this->json()['version']);
        self::assertSame(1, $this->rows('media_pending_deletions'));

        $this->remove($path, $this->editor, 2);
        self::assertSame(200, $this->code());
        self::assertSame(['version' => 3, 'image' => null], $this->json());
        self::assertSame(2, $this->rows('media_pending_deletions'));
        $this->call('GET', $path.'/content', $this->viewer);
        self::assertSame(404, $this->code());
        self::assertSame(3, $this->rows('media_audit'));
    }

    public function testAPublicKindIsServedToAnyoneByFileIdWithLongLivedCaching(): void
    {
        $path = '/api/media/event_banner/'.self::EVENT;
        $this->upload($path, $this->editor, 0, ImageFixtures::png(1600, 900));
        $image = $this->json()['image'];
        self::assertTrue($image['public']);
        self::assertMatchesRegularExpression('#\A/api/public/media/[0-9a-f-]{36}\z#', $image['url']);
        self::assertSame([['width' => 640, 'url' => $image['url'].'/640']], $image['variants']);

        $this->client->request('GET', $image['url']);
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/webp', $response->headers->get('Content-Type'));
        self::assertSame([1280, 720], array_slice(getimagesizefromstring((string) $response->getContent()) ?: [], 0, 2));
        $cache = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cache);
        self::assertStringContainsString('max-age=31536000', $cache);
        self::assertStringContainsString('immutable', $cache);
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('cross-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
        $etag = (string) $response->headers->get('ETag');
        self::assertNotSame('', $etag);

        $this->client->request('GET', $image['url'], server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertSame(304, $this->code());

        $this->client->request('GET', $image['url'].'/640');
        self::assertSame([640, 360], array_slice(getimagesizefromstring((string) $this->client->getResponse()->getContent()) ?: [], 0, 2));
        $this->client->request('GET', $image['url'].'/64');
        self::assertSame(404, $this->code(), 'a width the kind does not keep');
    }

    public function testPersonalPicturesAreNeverServedByThePublicRoute(): void
    {
        $this->upload('/api/media/person/'.self::PERSON, $this->editor, 0, ImageFixtures::png());
        $fileId = (string) $this->connection->fetchOne('SELECT file_id FROM media_slots');

        $this->client->request('GET', '/api/public/media/'.$fileId);
        self::assertSame(404, $this->code());
        $this->client->request('GET', '/api/public/media/'.$fileId.'/64');
        self::assertSame(404, $this->code());
    }

    public function testUnknownMalformedAndRemovedPublicPicturesAreA404(): void
    {
        $this->client->request('GET', '/api/public/media/'.self::EVENT);
        self::assertSame(404, $this->code());
        $this->client->request('GET', '/api/public/media/not-a-uuid');
        self::assertSame(404, $this->code());
        $this->client->request('GET', '/api/public/media/'.self::EVENT.'/abc');
        self::assertSame(404, $this->code());

        $path = '/api/media/event_banner/'.self::EVENT;
        $this->upload($path, $this->editor, 0, ImageFixtures::png());
        $url = (string) $this->json()['image']['url'];
        $this->remove($path, $this->editor, 1);
        $drain = self::getContainer()->get(\App\Media\Application\DeletionDrain::class);
        \assert($drain instanceof \App\Media\Application\DeletionDrain);
        $drain->run();
        $this->client->request('GET', $url);
        self::assertSame(404, $this->code(), 'once removed and deleted the old URL stops working');
    }

    public function testUploadsAreRateLimitedPerAccount(): void
    {
        $path = '/api/media/person/'.self::PERSON;
        for ($i = 0; $i < 30; ++$i) {
            $this->upload($path, $this->editor, 99, 'x');
            self::assertSame(409, $this->code(), 'a stale version: cheap, and it still counts against the limit');
        }

        $this->upload($path, $this->editor, 99, 'x');
        self::assertSame(429, $this->code());
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Retry-After'));

        $this->upload($path, $this->viewer, 0, 'x');
        self::assertSame(403, $this->code(), 'another account is not throttled by it');
    }

    public function testBrowsersMayAskToSendTheVersionHeaderAcrossOrigins(): void
    {
        $this->client->request('OPTIONS', '/api/media/person/'.self::PERSON, server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,x-image-version',
        ]);

        self::assertStringContainsStringIgnoringCase('x-image-version', (string) $this->client->getResponse()->headers->get('Access-Control-Allow-Headers'));
    }

    public function testAllPicturePathsBelongToMedia(): void
    {
        $router = self::getContainer()->get(RouterInterface::class);
        \assert($router instanceof RouterInterface);

        foreach (['event', 'community', 'event_banner', 'family', 'profile'] as $target) {
            self::assertStringContainsString('MediaController', (string) $router->match('/api/media/'.$target.'/'.self::EVENT)['_controller'], $target);
        }
    }

    private function upload(string $uri, ?string $token, int|string|null $version, string $bytes): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'media');
        file_put_contents($file, $bytes);
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        if (null !== $version) {
            $server['HTTP_X_IMAGE_VERSION'] = (string) $version;
        }
        $this->client->request('POST', $uri, files: ['image' => new UploadedFile($file, 'photo.png', 'image/png', test: true)], server: $server);
    }

    private function remove(string $uri, ?string $token, int $version): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_IMAGE_VERSION' => (string) $version];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request('DELETE', $uri, server: $server);
    }
}

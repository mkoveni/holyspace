<?php

declare(strict_types=1);

namespace App\Tests\Communities\Presentation;

use App\Tests\Media\Infrastructure\ImageFixtures;
use App\Tests\Support\CoverImageFixtures;

/** A community's picture is a Media `community` image: the owner rules, and `coverImageUrl` for members. */
final class CommunityCoverApiTest extends CommunitiesApiTestCase
{
    use CoverImageFixtures;

    private string $group;
    private string $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = $this->community('GROUP', 'Home group');
        $account = $this->account('member');
        $self = $this->person('Member');
        $this->call('PUT', "/api/member-access/$account", $this->admin, ['personId' => $self, 'active' => true, 'expectedVersion' => 0, 'evidence' => 'Identity verified in person']);
        self::assertSame(204, $this->code());
        $this->member = $this->login('member');
        $this->join($this->group, $self);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg('/tmp/onechurch-media-test'));
        parent::tearDown();
    }

    private function path(?string $community = null): string
    {
        return '/api/media/community/'.($community ?? $this->group);
    }

    private function memberCover(): ?string
    {
        $this->call('GET', "/api/member/communities/{$this->group}", $this->member);
        self::assertSame(200, $this->code());
        $url = $this->json()['community']['coverImageUrl'];
        $this->call('GET', '/api/member/communities', $this->member);
        self::assertSame($url, $this->json()['items'][0]['coverImageUrl'], 'the list and the detail agree');

        return null === $url ? null : (string) $url;
    }

    public function testUploadAndRemoveRoundTripToTheMemberApi(): void
    {
        self::assertNull($this->memberCover());
        $this->call('GET', $this->path(), $this->admin);
        self::assertSame(['version' => 0, 'image' => null], $this->json());

        $this->uploadCover($this->path(), $this->admin, 0, ImageFixtures::png(1600, 900));
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([1, 'image/webp', 1280, 720], [$this->json()['version'], $this->json()['image']['mime'], $this->json()['image']['width'], $this->json()['image']['height']]);
        $url = $this->memberCover();
        self::assertSame($this->json()['items'][0]['coverImageUrl'], $url);
        self::assertSame($url, $this->staffUrl());
        self::assertMatchesRegularExpression('#\A/api/public/media/[0-9a-f-]{36}\z#', (string) $url);
        $this->fetchPublicCover((string) $url);
        self::assertSame([200, 'image/webp'], [$this->code(), $this->client->getResponse()->headers->get('Content-Type')]);

        $this->removeCover($this->path(), $this->admin, 1);
        self::assertSame(['version' => 2, 'image' => null], $this->json());
        self::assertNull($this->memberCover());
        self::assertSame(2, $this->rows('media_audit'));
    }

    public function testOnlyManagersChangePicturesAndLeadersMayLook(): void
    {
        $other = $this->community('GROUP', 'Other group');
        $leader = $this->leader('leader', $this->join($other, $this->person('Leader')));
        $this->uploadCover($this->path(), $leader, 0, ImageFixtures::png());
        self::assertSame(403, $this->code());
        $this->uploadCover($this->path(), $this->member, 0, ImageFixtures::png());
        self::assertSame(403, $this->code());
        $this->uploadCover($this->path(), null, 0, ImageFixtures::png());
        self::assertSame(401, $this->code());
        $this->call('GET', $this->path($other), $leader);
        self::assertSame(200, $this->code());

        $this->uploadCover($this->path(), $this->admin, null, ImageFixtures::png());
        self::assertSame(400, $this->code());
        $this->uploadCover($this->path(), $this->admin, 3, ImageFixtures::png());
        self::assertSame(409, $this->code());
        $this->uploadCover($this->path(), $this->admin, 0, 'GIF89a');
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('media_files'), 'failed commands store nothing');
    }

    private function staffUrl(): ?string
    {
        $this->call('GET', $this->path(), $this->admin);

        return $this->json()['image']['url'] ?? null;
    }
}

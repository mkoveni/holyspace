<?php

declare(strict_types=1);

namespace App\Tests\Media\Presentation;

use App\Tests\Media\Infrastructure\ImageFixtures;
use App\Tests\Support\IdentityApiTestCase;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/** `app:media:import-covers` and the redirect that keeps cached `coverImageUrl` values working. */
final class LegacyCoverImportTest extends IdentityApiTestCase
{
    private const string EVENT = 'e0000000-0000-4000-8000-000000000001';
    private const string COMMUNITY = 'c0000000-0000-4000-8000-000000000001';
    private const string EVENT_IMAGE = 'd0000000-0000-4000-8000-000000000001';
    private const string COMMUNITY_IMAGE = 'd0000000-0000-4000-8000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection->executeStatement("INSERT INTO event_definitions (id, title, type, active, version) VALUES (?, 'Conference', 'CONFERENCE', TRUE, 0)", [self::EVENT]);
        $this->connection->executeStatement("INSERT INTO communities (id, kind, name, description) VALUES (?, 'GROUP', 'Home group', '')", [self::COMMUNITY]);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg('/tmp/onechurch-media-test'));
        parent::tearDown();
    }

    public function testCoversMoveIntoMediaKeepingTheirIdAndTheOldUrlRedirects(): void
    {
        $this->legacy('event_cover_images', 'event_id', self::EVENT, self::EVENT_IMAGE, 3, ImageFixtures::png(1600, 900));
        $this->legacy('community_cover_images', 'community_id', self::COMMUNITY, self::COMMUNITY_IMAGE, 1, ImageFixtures::jpeg(800, 450));

        $output = $this->import();

        self::assertSame('Imported 2, skipped 0, failed 0.', $output);
        self::assertSame(2, $this->rows('media_files'));
        $row = $this->connection->fetchAssociative('SELECT kind, owner_id, width, height, mime FROM media_files WHERE id = ?', [self::EVENT_IMAGE]);
        self::assertSame(['event', self::EVENT, 1280, 720, 'image/webp'], array_values((array) $row));
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT version FROM media_slots WHERE owner_id = ? AND kind = 'event'", [self::EVENT]));

        $this->client->request('GET', '/api/public/cover-images/'.self::EVENT_IMAGE);
        self::assertSame(301, $this->code());
        self::assertSame('/api/public/media/'.self::EVENT_IMAGE, $this->client->getResponse()->headers->get('Location'));
        $this->client->request('GET', '/api/public/media/'.self::EVENT_IMAGE);
        self::assertSame([200, 'image/webp'], [$this->code(), $this->client->getResponse()->headers->get('Content-Type')]);
        $this->client->request('GET', '/api/public/media/'.self::EVENT_IMAGE.'/640');
        self::assertSame(200, $this->code());
    }

    public function testRunningItAgainChangesNothingAndNeverBringsARemovedCoverBack(): void
    {
        $this->legacy('event_cover_images', 'event_id', self::EVENT, self::EVENT_IMAGE, 1, ImageFixtures::png(1600, 900));
        self::assertSame('Imported 1, skipped 0, failed 0.', $this->import());

        self::assertSame('Imported 0, skipped 1, failed 0.', $this->import());
        self::assertSame(1, $this->rows('media_files'));

        // Staff removed the picture after the import: the slot is empty again at a higher version.
        $this->connection->executeStatement('UPDATE media_slots SET file_id = NULL, version = 2');
        $this->connection->executeStatement('DELETE FROM media_files');

        self::assertSame('Imported 0, skipped 1, failed 0.', $this->import());
        self::assertSame(0, $this->rows('media_files'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT version FROM media_slots'));
    }

    public function testACorruptCoverFailsAloneAndTheRestStillImport(): void
    {
        $this->legacy('event_cover_images', 'event_id', self::EVENT, self::EVENT_IMAGE, 1, 'not an image');
        $this->legacy('community_cover_images', 'community_id', self::COMMUNITY, self::COMMUNITY_IMAGE, 1, ImageFixtures::png(1600, 900));

        $tester = $this->importer();

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Imported 1, skipped 0, failed 1.', $tester->getDisplay());
        self::assertSame(1, $this->rows('media_files'));
    }

    public function testAnIdThatWasNeverImportedRedirectsToANotFound(): void
    {
        $this->client->request('GET', '/api/public/cover-images/'.self::EVENT_IMAGE);
        self::assertSame(301, $this->code());
        $this->client->request('GET', '/api/public/media/'.self::EVENT_IMAGE);
        self::assertSame(404, $this->code());
        $this->client->request('GET', '/api/public/cover-images/not-a-uuid');
        self::assertSame(400, $this->code());
    }

    private function import(): string
    {
        return trim($this->importer()->getDisplay());
    }

    private function importer(): CommandTester
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('app:media:import-covers'));
        $tester->execute([]);

        return $tester;
    }

    private function legacy(string $table, string $column, string $owner, string $image, int $version, string $bytes): void
    {
        $this->connection->executeStatement(
            "INSERT INTO cover_images (id, content_type, width, height, byte_size, content, created_at) VALUES (?, 'image/png', 1600, 900, ?, ?, now())",
            [$image, \strlen($bytes), $bytes],
            [ParameterType::STRING, ParameterType::INTEGER, ParameterType::LARGE_OBJECT],
        );
        $this->connection->executeStatement(sprintf('INSERT INTO %s (%s, image_id, version, updated_at) VALUES (?, ?, ?, now())', $table, $column), [$owner, $image, $version]);
    }
}

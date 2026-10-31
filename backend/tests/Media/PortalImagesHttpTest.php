<?php

declare(strict_types=1);

namespace App\Tests\Media;

use App\Media\Application\ImageTargets;
use App\Media\Domain\ImageStorage;
use App\Media\Infrastructure\DbalImageStorage;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowTransactionManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use OutOfBoundsException;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class PortalImagesHttpTest extends WebTestCase
{
    private $client;
    private Connection $db;
    private ImageAccess $access;
    private TestImageTargets $targets;
    private string $id;
    private array $files = [];

    protected static function getKernelClass(): string
    {
        return ImagesTestKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient(['debug' => false]);
        $this->client->disableReboot();
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->db->executeStatement('CREATE TABLE media_images(target TEXT,owner_id TEXT,version INT,mime TEXT,width INT,height INT,byte_size INT,content BLOB,updated_at TEXT,PRIMARY KEY(target,owner_id))');
        $this->db->executeStatement('CREATE TABLE workflow_audit(id TEXT PRIMARY KEY,context TEXT,record_id TEXT,kind TEXT,action TEXT,version INT,actor_id TEXT,occurred_at TEXT,UNIQUE(context,record_id,version))');
        $this->id = Uuid::v7()->toRfc4122();
        $this->targets = new TestImageTargets($this->id);
        $this->access = new ImageAccess();
        self::getContainer()->set(Connection::class, $this->db);
        self::getContainer()->set(ImageTargets::class, $this->targets);
        self::getContainer()->set(AuthorizationCheckerInterface::class, $this->access);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function image(string $format = 'png', int $width = 32): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'image-test');
        $this->files[] = $path;
        $image = imagecreatetruecolor($width, 16);
        imagefill($image, 0, 0, imagecolorallocate($image, 14, 80, 160));
        match ($format) {
            'jpeg' => imagejpeg($image, $path),
            'webp' => imagewebp($image, $path),
            default => imagepng($image, $path),
        };
        imagedestroy($image);
        file_put_contents($path, 'untrusted metadata', FILE_APPEND);

        return new UploadedFile($path, 'camera.'.$format, 'application/octet-stream', null, true);
    }

    private function upload(string $target = 'person', int $version = 0, ?UploadedFile $file = null, int $status = 200): array
    {
        $this->client->request('POST', '/api/media/'.$target.'/'.$this->id, files: ['image' => $file ?? $this->image()], server: ['HTTP_X_IMAGE_VERSION' => (string) $version]);
        self::assertResponseStatusCodeSame($status);

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    public function testAllFourSlotsStoreSanitizedImagesAndRespectReadAndManagePermissions(): void
    {
        foreach (['person' => 'people', 'family' => 'families', 'event' => 'events', 'form' => 'forms'] as $target => $context) {
            $this->access->codes = [$context.'.read'];
            $this->upload($target, status: 403);
            $this->access->codes[] = $context.'.manage';
            $saved = $this->upload($target);
            self::assertSame(1, $saved['version']);
            self::assertSame(32, $saved['image']['width']);
            self::assertArrayNotHasKey('content', $saved['image']);
            $this->client->request('GET', '/api/media/'.$target.'/'.$this->id.'/content');
            self::assertResponseIsSuccessful();
            $response = $this->client->getResponse();
            self::assertSame('image/png', $response->headers->get('Content-Type'));
            self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            self::assertStringNotContainsString('untrusted metadata', $response->getContent());
            self::assertSame(IMAGETYPE_PNG, getimagesizefromstring($response->getContent())[2]);
            $this->access->codes = [];
            $this->client->request('GET', '/api/media/'.$target.'/'.$this->id.'/content');
            self::assertResponseStatusCodeSame(403);
        }
        self::assertSame(4, (int) $this->db->fetchOne('SELECT COUNT(*) FROM workflow_audit'));
    }

    public function testReplaceRemoveAndStaleVersionsCannotOverwriteAnotherUpload(): void
    {
        $this->upload();
        $this->upload(version: 0, status: 409);
        $this->upload(version: 1);
        $this->client->request('DELETE', '/api/media/person/'.$this->id, server: ['HTTP_X_IMAGE_VERSION' => '1']);
        self::assertResponseStatusCodeSame(409);
        $this->client->request('DELETE', '/api/media/person/'.$this->id, server: ['HTTP_X_IMAGE_VERSION' => '2']);
        self::assertResponseIsSuccessful();
        self::assertNull(json_decode($this->client->getResponse()->getContent(), true)['image']);
        $this->client->request('GET', '/api/media/person/'.$this->id.'/content');
        self::assertResponseStatusCodeSame(404);
        $this->upload(version: 0, status: 409);
        $this->upload(version: 3);
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM media_images'));
        self::assertSame(4, (int) $this->db->fetchOne('SELECT COUNT(*) FROM workflow_audit'));
    }

    public function testPublicBannerRequiresCurrentPublishedFormAndNeverExposesPrivatePhotos(): void
    {
        $this->upload('person');
        $this->upload('form');
        $this->access->codes = [];
        $path = '/api/public/forms/'.str_repeat('a', 64).'/banner';
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
        $this->targets->published = true;
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/public/forms/'.str_repeat('b', 64).'/banner');
        self::assertResponseStatusCodeSame(404);
        $this->targets->published = false;
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/media/person/'.$this->id.'/content');
        self::assertResponseStatusCodeSame(403);
    }

    public function testPublicEventBannerIsScopedToAPublishedFormToken(): void
    {
        $this->upload('event');
        $this->upload('person');
        $this->access->codes = [];
        $path = '/api/public/forms/'.str_repeat('a', 64).'/event-banner';
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
        $this->targets->published = true;
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $this->targets->published = false;
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/public/forms/'.str_repeat('b', 64).'/event-banner');
        self::assertResponseStatusCodeSame(404);
    }

    public function testMissingOwnerMalformedVersionAndUnsafeOrExcessiveFilesAreRejected(): void
    {
        $this->client->request('GET', '/api/media/person/'.Uuid::v4()->toRfc4122());
        self::assertResponseStatusCodeSame(404);
        $this->upload('unknown', status: 422);
        $this->client->request('DELETE', '/api/media/person/'.$this->id);
        self::assertResponseStatusCodeSame(422);
        $path = tempnam(sys_get_temp_dir(), 'invalid-image');
        $this->files[] = $path;
        foreach (['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', str_repeat('x', 5 * 1024 * 1024 + 1)] as $bytes) {
            file_put_contents($path, $bytes);
            $this->upload(file: new UploadedFile($path, 'photo.png', 'image/png', null, true), status: 422);
        }
        $this->upload(file: $this->image(width: 12001), status: 422);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM media_images'));
    }

    public function testAuditFailureRollsBackTheImageAndJpegOrientationIsApplied(): void
    {
        $auditId = Uuid::v5(Uuid::fromString($this->id), 'portal-image:person')->toRfc4122();
        $this->db->insert('workflow_audit', ['id' => Uuid::v7()->toRfc4122(), 'context' => 'people', 'record_id' => $auditId, 'kind' => 'image', 'action' => 'created', 'version' => 1]);
        $this->upload(status: 409);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM media_images'));
        $this->db->executeStatement('DELETE FROM workflow_audit');
        if (!function_exists('imagejpeg')) {
            self::markTestSkipped('JPEG enabled in rebuilt runtime.');
        }
        $image = $this->image('jpeg');
        $path = $image->getPathname();
        $exif = "Exif\x00\x00II".pack('vV', 42, 8).pack('v', 1).pack('vvVv', 0x112, 3, 1, 6)."\x00\x00".pack('V', 0);
        $bytes = file_get_contents($path);
        file_put_contents($path, substr($bytes, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($bytes, 2));
        $saved = $this->upload(file: $image);
        self::assertSame(16, $saved['image']['width']);
        self::assertSame(32, $saved['image']['height']);
    }

    public function testLargeImagesResizeAndJpegWebpDecodeInProductionRuntime(): void
    {
        foreach (['png', 'jpeg', 'webp'] as $index => $format) {
            if ('jpeg' === $format && !function_exists('imagejpeg') || 'webp' === $format && !function_exists('imagewebp')) {
                self::markTestSkipped('Rebuild development PHP with JPEG/WebP support; fresh production runtime tests all formats.');
            }
            $saved = $this->upload(version: $index, file: $this->image($format, 2400));
            self::assertSame(1920, $saved['image']['width']);
        }
    }
}
final class ImageAccess implements AuthorizationCheckerInterface
{
    public array $codes = ['people.read', 'people.manage', 'families.read', 'families.manage', 'events.read', 'events.manage', 'forms.read', 'forms.manage'];

    public function isGranted(mixed $attribute, mixed $subject = null, ?\Symfony\Component\Security\Core\Authorization\AccessDecision $accessDecision = null): bool
    {
        return in_array($attribute, $this->codes, true);
    }
}
final class TestImageTargets implements ImageTargets
{
    public bool $published = false;

    public function __construct(private string $id)
    {
    }

    public function require(string $target, string $id): void
    {
        if ($id !== $this->id) {
            throw new OutOfBoundsException();
        }
    }

    public function publishedEventId(string $token): string
    {
        return $this->publishedFormId($token);
    }

    public function publishedFormId(string $token): string
    {
        if (!$this->published || $token !== str_repeat('a', 64)) {
            throw new OutOfBoundsException();
        }

        return $this->id;
    }
}
final class ImagesTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return '/tmp/onechurch-images-tests/cache';
    }

    public function getLogDir(): string
    {
        return '/tmp/onechurch-images-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'images-test', 'test' => true, 'serializer' => ['enabled' => true], 'validation' => ['enabled' => true], 'exceptions' => [AccessDeniedException::class => ['status_code' => 403]]]);
        $s = $container->services()->defaults()->autowire()->autoconfigure();
        \App\Tests\Support\ApplicationPorts::reads($s);
        foreach ([Connection::class, ImageTargets::class, AuthorizationCheckerInterface::class] as $class) {
            $s->set($class)->synthetic()->public();
        }
        $s->alias('security.authorization_checker', AuthorizationCheckerInterface::class);
        $s->set(WorkflowAuditJournal::class);
        $s->set(WorkflowTransactionManager::class);
        $s->alias(WorkflowTransaction::class, WorkflowTransactionManager::class);
        $s->set(\Symfony\Component\Lock\Store\FlockStore::class);
        $s->set(\Symfony\Component\Lock\LockFactory::class)->args([\Symfony\Component\DependencyInjection\Loader\Configurator\service(\Symfony\Component\Lock\Store\FlockStore::class)]);
        $base = dirname(__DIR__, 2).'/src/Media';
        $s->load('App\Media\Application\\', $base.'/Application/');
        $s->set(\App\Media\Infrastructure\RasterImageProcessor::class);
        $s->alias(\App\Media\Application\Port\ImageProcessor::class, \App\Media\Infrastructure\RasterImageProcessor::class);
        \App\Tests\Support\ApplicationPorts::access($s);
        $s->alias(\App\Media\Application\ReadModel\ImageReader::class, DbalImageStorage::class);
        $s->set(\App\Shared\Infrastructure\Http\DomainExceptionListener::class);
        $s->set(DbalImageStorage::class);
        $s->alias(ImageStorage::class, DbalImageStorage::class);
        $s->load('App\Media\Presentation\\', $base.'/Presentation/');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(dirname(__DIR__, 2).'/src/Media/Presentation/Http/', 'attribute');
    }
}

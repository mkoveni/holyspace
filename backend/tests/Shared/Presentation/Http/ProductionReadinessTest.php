<?php

declare(strict_types=1);

namespace App\Tests\Shared\Presentation\Http;

use App\IdentityAccess\Infrastructure\Http\ContextAccessListener;
use App\People\Presentation\Http\Controller\ReadinessController;
use App\Shared\Infrastructure\Operations\Readiness;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProductionReadinessTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return ProductionReadinessKernel::class;
    }

    public function testLegacyRoutesDenyEveryReadAndWriteWithoutPermission(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->disableReboot();
        $authorization = $this->createStub(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn(false);
        self::getContainer()->set(AuthorizationCheckerInterface::class, $authorization);
        foreach (['people', 'people/id/addresses', 'relationships/id/end', 'families/id/members', 'family-overviews', 'events/id/occurrences/other', 'life-events/id'] as $path) {
            foreach (['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
                $client->request($method, '/api/'.$path);
                self::assertResponseStatusCodeSame(403);
                self::assertFalse($client->getResponse()->headers->has('X-Controller-Ran'));
            }
        }
    }

    public function testReadPermissionCannotWriteAndManageCanWrite(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->disableReboot();
        $authorization = $this->createStub(AuthorizationCheckerInterface::class);
        $permissions = ['people.read'];
        $authorization->method('isGranted')->willReturnCallback(static function ($permission) use (&$permissions) {
            return in_array($permission, $permissions, true);
        });
        self::getContainer()->set(AuthorizationCheckerInterface::class, $authorization);
        $client->request('GET', '/api/people/search');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/api/people');
        self::assertResponseStatusCodeSame(403);
        $permissions[] = 'people.manage';
        $client->request('POST', '/api/people');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Controller-Ran', 'yes');
    }

    public function testReadinessFailsClosedUntilDependenciesAndPermissionsExist(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->disableReboot();
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        self::getContainer()->set(Connection::class, $db);
        self::getContainer()->set(AuthorizationCheckerInterface::class, $this->createStub(AuthorizationCheckerInterface::class));
        $client->request('GET', '/health/ready');
        self::assertResponseStatusCodeSame(503);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(['status' => 'unavailable', 'checks' => ['database' => true, 'schema' => false]], $body);
        foreach (['permissions(code TEXT)', 'integration_outbox(id TEXT)', 'workflow_audit(id TEXT)', 'account_security(user_id TEXT)', 'account_security_audit(id TEXT)', 'reporting_records(id TEXT,kind TEXT,payload TEXT)', 'payments_records(id TEXT,kind TEXT,payload TEXT)', 'payment_notifications(id TEXT)', 'media_images(owner_id TEXT)'] as $table) {
            $db->executeStatement('CREATE TABLE '.$table);
        }
        foreach (['people', 'families', 'events', 'life_events'] as $context) {
            foreach (['read', 'manage'] as $action) {
                $db->insert('permissions', ['code' => $context.'.'.$action]);
            }
        }
        $client->request('GET', '/health/ready');
        self::assertResponseIsSuccessful();
        self::assertTrue(\Symfony\Component\Uid\Uuid::isValid($client->getResponse()->headers->get('X-Request-ID')));
        self::assertSame('ready', json_decode($client->getResponse()->getContent(), true)['status']);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
    }
}
final class ProductionFixtureController
{
    #[Route('/api/{path}', requirements: ['path' => '.+'], methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['ran' => true], 200, ['X-Controller-Ran' => 'yes']);
    }
}
final class ProductionReadinessKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/onechurch-production-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/onechurch-production-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'production-test', 'test' => true, 'router' => ['utf8' => true]]);
        $services = $container->services()->defaults()->autowire()->autoconfigure();
        \App\Tests\Support\ApplicationPorts::reads($services);
        $services->set(AuthorizationCheckerInterface::class)->synthetic()->public();
        $services->set(Connection::class)->synthetic()->public();
        $services->set(\Psr\Log\LoggerInterface::class, \Psr\Log\NullLogger::class);
        $services->set(\App\Shared\Infrastructure\Http\RequestIdListener::class);
        foreach ([ContextAccessListener::class, Readiness::class, ReadinessController::class, ProductionFixtureController::class] as $class) {
            $services->set($class)->public();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('production_fixture', '/api/{path}')->controller([ProductionFixtureController::class, '__invoke'])->requirements(['path' => '.+'])->methods(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
        $routes->import((new ReflectionClass(ReadinessController::class))->getFileName(), 'attribute');
    }
}

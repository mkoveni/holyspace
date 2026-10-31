<?php

declare(strict_types=1);

namespace App\Tests\Events;

use App\Events\Domain\Repository\EventRepository;
use App\Events\Infrastructure\Persistence\DBAL\DbalEventRepository;
use App\Events\Infrastructure\Persistence\DBAL\EventMapper;
use App\Events\Presentation\Http\Controller\EventsController;
use Doctrine\DBAL\DriverManager;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class EventsHttpTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return EventsTestKernel::class;
    }

    public function testCompleteEventWorkflowThroughHttpAndDatabase(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->disableReboot();
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE church_events(id TEXT PRIMARY KEY,title TEXT,type TEXT,description TEXT,timezone TEXT,recurrence TEXT,created_at TEXT,updated_at TEXT)');
        $db->executeStatement('CREATE TABLE church_event_occurrences(id TEXT PRIMARY KEY,event_id TEXT,starts_at TEXT,ends_at TEXT,location TEXT,status TEXT,created_at TEXT,updated_at TEXT)');
        $db->executeStatement('CREATE TABLE integration_outbox(sequence_no INTEGER PRIMARY KEY AUTOINCREMENT,id TEXT,type TEXT,schema_version INTEGER,payload TEXT,created_at TEXT,processed_at TEXT)');
        self::getContainer()->set(\App\Shared\Application\WorkflowTransaction::class, new class implements \App\Shared\Application\WorkflowTransaction {
            public function run(callable $operation): mixed
            {
                return $operation();
            }
        });
        self::getContainer()->set(EventRepository::class, new DbalEventRepository($db, new EventMapper()));
        $payload = ['title' => 'Sunday service', 'type' => 'service', 'startsAt' => '2026-12-27T09:00', 'endsAt' => '2026-12-27T11:00', 'location' => 'Main church', 'timezone' => 'Africa/Johannesburg', 'recurrence' => 'weekly', 'occurrenceCount' => 3];
        $client->jsonRequest('POST', '/api/events', $payload);
        self::assertResponseStatusCodeSame(201);
        $id = json_decode($client->getResponse()->getContent(), true)['id'];
        $client->request('GET', '/api/events/'.$id);
        self::assertResponseIsSuccessful();
        $event = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Sunday service', $event['title']);
        self::assertSame('Africa/Johannesburg', $event['timezone']);
        self::assertSame(['2026-12-27T09:00:00+02:00', '2027-01-03T09:00:00+02:00', '2027-01-10T09:00:00+02:00'], array_column($event['occurrences'], 'startsAt'));
        self::assertCount(3, array_unique(array_column($event['occurrences'], 'id')));
        self::assertSame('2026-12-27T07:00:00+00:00', $db->fetchOne('SELECT starts_at FROM church_event_occurrences WHERE id = ?', [$event['occurrences'][0]['id']]));
        $occurrenceId = $event['occurrences'][1]['id'];
        $changed = ['startsAt' => '2027-01-03T10:00', 'endsAt' => '2027-01-03T12:00', 'location' => 'Community hall', 'status' => 'cancelled'];
        $client->jsonRequest('PUT', '/api/events/'.$id.'/occurrences/'.$occurrenceId, $changed);
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/events/'.$id);
        $updated = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($occurrenceId, $updated['occurrences'][1]['id']);
        self::assertSame('cancelled', $updated['occurrences'][1]['status']);
        self::assertSame('Community hall', $updated['occurrences'][1]['location']);
        self::assertSame('2027-01-03T10:00:00+02:00', $updated['occurrences'][1]['startsAt']);
        self::assertSame($event['occurrences'][0], $updated['occurrences'][0]);
        self::assertSame($event['occurrences'][2], $updated['occurrences'][2]);
        $changed['status'] = 'scheduled';
        $client->jsonRequest('PUT', '/api/events/'.$id.'/occurrences/'.$occurrenceId, $changed);
        self::assertResponseStatusCodeSame(204);
        $client->jsonRequest('PUT', '/api/events/'.$id, ['title' => 'Morning worship', 'type' => 'service', 'description' => 'All welcome']);
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/events');
        $listed = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $listed);
        self::assertSame('Morning worship', $listed[0]['title']);
        self::assertSame('scheduled', $listed[0]['occurrences'][1]['status']);
        self::assertSame($event['createdAt'], $listed[0]['createdAt']);
        // Invalid inputs produce caller-facing errors, never partial schedules.
        foreach ([['title' => ''], ['occurrenceCount' => 53], ['timezone' => 'invalid'], ['startsAt' => '2026-02-30T09:00'], ['endsAt' => '2026-12-27T08:00'], ['recurrence' => 'once', 'occurrenceCount' => 3]] as $invalid) {
            $client->jsonRequest('POST', '/api/events', array_replace($payload, $invalid));
            self::assertResponseStatusCodeSame(422);
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM church_events'));
        }
        $client->request('GET', '/api/events/not-a-uuid');
        self::assertResponseStatusCodeSame(422);
        $missingId = '018f3a7b-4e4a-7a22-9b0e-111111111111';
        $client->request('GET', '/api/events/'.$missingId);
        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('PUT', '/api/events/'.$id.'/occurrences/'.$missingId, $changed);
        self::assertResponseStatusCodeSame(404);
    }
}
final class EventsTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/onechurch-events-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/onechurch-events-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'events-tests', 'test' => true, 'router' => ['utf8' => true], 'serializer' => ['enabled' => true], 'validation' => ['enabled' => true], 'messenger' => ['enabled' => true]]);
        $services = $container->services()->defaults()->autowire()->autoconfigure();
        \App\Tests\Support\ApplicationPorts::reads($services);
        $services->set(EventRepository::class)->synthetic()->public();
        $services->set(\App\Shared\Application\WorkflowTransaction::class)->synthetic()->public();
        $source = dirname((new ReflectionClass(EventsController::class))->getFileName(), 4);
        foreach ([\App\Events\Application\Command\CreateEventHandler::class, \App\Events\Application\Command\UpdateEventHandler::class, \App\Events\Application\Command\UpdateOccurrenceHandler::class, \App\Events\Application\Query\GetEvent\GetEventHandler::class, \App\Events\Application\Query\ListEvents\ListEventsHandler::class] as $handler) {
            $services->set($handler);
        }
        $services->set(EventsController::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import((new ReflectionClass(EventsController::class))->getFileName(), 'attribute');
    }
}

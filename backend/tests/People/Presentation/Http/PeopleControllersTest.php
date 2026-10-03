<?php

declare(strict_types=1);

namespace App\Tests\People\Presentation\Http;

use App\People\Application\Command\AddAddress\AddAddressCommand;
use App\People\Application\Command\AddFamilyMember\AddFamilyMemberCommand;
use App\People\Application\Command\CreateFamily\CreateFamilyCommand;
use App\People\Application\Command\CreateRelationship\CreateRelationshipCommand;
use App\People\Application\Command\RegisterPerson\RegisterPersonCommand;
use App\People\Application\ReadModel\PeopleReadRepository;
use App\People\Domain\ValueObject\PersonId;
use App\People\Presentation\Http\Controller\PeopleController;
use App\People\Presentation\Http\Service\CommandDispatcher;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class PeopleControllersTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return PeopleHttpTestKernel::class;
    }

    public function testPeopleRegistrationPreservesDefaultsAndCreatedResponse(): void
    {
        $this->assertCommandRequest('/api/people', ['firstName' => 'Jane', 'lastName' => 'Doe'],
            new RegisterPersonCommand('Jane', 'Doe', null, 'unspecified', null, null));
    }

    public function testFamilyCreationUsesTheFamilyController(): void
    {
        $this->assertCommandRequest('/api/families', ['name' => 'Doe'], new CreateFamilyCommand('Doe'));
    }

    public function testProfileAddressPreservesRouteIdAndDefaults(): void
    {
        $this->assertCommandRequest('/api/people/person-123/addresses', ['line1' => '123 Main Road', 'city' => 'Cape Town'],
            new AddAddressCommand('person-123', 'residential', '123 Main Road', null, 'Cape Town', null, null, 'South Africa'));
    }

    public function testRelationshipCreationPreservesRouteId(): void
    {
        $this->assertCommandRequest('/api/people/person-123/relationships', ['relatedPersonId' => 'person-456', 'type' => 'spouse'],
            new CreateRelationshipCommand('person-123', 'person-456', 'spouse'));
    }

    public function testAddingFamilyMemberReturnsNoContent(): void
    {
        $this->assertCommandRequest('/api/families/family-123/members', ['personId' => 'person-456'],
            new AddFamilyMemberCommand('family-123', 'person-456', 'other'), 204);
    }

    public function testPersonFamiliesDelegatesToReadModelAndPreservesJson(): void
    {
        $client = self::createClient(['debug' => false]);
        $read = $this->createMock(PeopleReadRepository::class);
        $read->expects(self::once())->method('familiesForPerson')->with('person-123')
            ->willReturn([['id' => 'family-123', 'name' => 'Doe', 'role' => 'other', 'membership_created_at' => '2026-10-03', 'created_at' => '2026-10-01', 'updated_at' => '2026-10-02']]);
        self::getContainer()->set(PeopleReadRepository::class, $read);
        self::getContainer()->set(MessageBusInterface::class, $this->createStub(MessageBusInterface::class));
        $client->request('GET', '/api/people/person-123/families');
        self::assertResponseIsSuccessful();
        self::assertSame([['id' => 'family-123', 'name' => 'Doe', 'role' => 'other', 'membershipCreatedAt' => '2026-10-03', 'createdAt' => '2026-10-01', 'updatedAt' => '2026-10-02']], json_decode($client->getResponse()->getContent(), true));
    }

    public function testProfileReadPreservesResponseKeys(): void
    {
        $client = self::createClient(['debug' => false]);
        $read = $this->createMock(PeopleReadRepository::class);
        $read->expects(self::once())->method('profile')->with('person-123')->willReturn([
            'addresses' => [], 'communications' => [['channel' => 'email']], 'education' => [], 'employment' => [],
        ]);
        self::getContainer()->set(PeopleReadRepository::class, $read);
        self::getContainer()->set(MessageBusInterface::class, $this->createStub(MessageBusInterface::class));
        $client->request('GET', '/api/people/person-123/profile');
        self::assertResponseIsSuccessful();
        self::assertSame([
            'addresses' => [], 'communicationOptions' => [['channel' => 'email']], 'education' => [], 'employment' => [],
        ], json_decode($client->getResponse()->getContent(), true));
    }

    private function assertCommandRequest(string $path, array $payload, object $expectedCommand, int $status = 201): void
    {
        $client = self::createClient(['debug' => false]);
        $id = PersonId::generate();
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::equalTo($expectedCommand))
            ->willReturn(new Envelope($expectedCommand, [new HandledStamp($id, 'test_handler')]));
        self::getContainer()->set(MessageBusInterface::class, $bus);
        self::getContainer()->set(PeopleReadRepository::class, $this->createStub(PeopleReadRepository::class));
        $client->jsonRequest('POST', $path, $payload);
        self::assertResponseStatusCodeSame($status);
        if (201 === $status) {
            self::assertSame(['id' => $id->toString()], json_decode($client->getResponse()->getContent(), true));
        } else {
            self::assertSame('', $client->getResponse()->getContent());
        }
    }
}

/** Isolate the People HTTP boundary from application authentication and database configuration. */
final class PeopleHttpTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/onechurch-people-http-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/onechurch-people-http-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'people-http-tests', 'test' => true, 'router' => ['utf8' => true]]);
        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(MessageBusInterface::class)->synthetic()->public();
        $services->set(PeopleReadRepository::class)->synthetic()->public();
        $services->set(CommandDispatcher::class);
        $source = dirname((new \ReflectionClass(PeopleController::class))->getFileName(), 4);
        $services->load('App\\People\\Presentation\\Http\\Controller\\', $source.'/Presentation/Http/Controller/*')->public();
        $services->load('App\\People\\Application\\Query\\', $source.'/Application/Query/*/*Handler.php');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(dirname((new \ReflectionClass(PeopleController::class))->getFileName()).'/', 'attribute');
    }
}

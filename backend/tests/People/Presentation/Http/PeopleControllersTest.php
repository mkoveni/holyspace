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
use ReflectionClass;
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
        $this->assertCommandRequest('/api/people', ['firstName' => 'Jane', 'lastName' => 'Doe'], new RegisterPersonCommand('Jane', 'Doe', null, 'unspecified', null, null));
    }

    public function testFamilyCreationUsesTheFamilyController(): void
    {
        $this->assertCommandRequest('/api/families', ['name' => 'Doe'], new CreateFamilyCommand('Doe'));
    }

    public function testProfileAddressPreservesRouteIdAndDefaults(): void
    {
        $this->assertCommandRequest('/api/people/person-123/addresses', ['line1' => '123 Main Road', 'city' => 'Cape Town'], new AddAddressCommand('person-123', 'residential', '123 Main Road', null, 'Cape Town', null, null, 'South Africa'));
    }

    public function testRelationshipCreationPreservesRouteId(): void
    {
        $this->assertCommandRequest('/api/people/person-123/relationships', ['relatedPersonId' => 'person-456', 'type' => 'spouse'], new CreateRelationshipCommand('person-123', 'person-456', 'spouse'));
    }

    public function testAddingFamilyMemberReturnsNoContent(): void
    {
        $this->assertCommandRequest('/api/families/family-123/members', ['personId' => 'person-456'], new AddFamilyMemberCommand('family-123', 'person-456', 'other'), 204);
    }

    public function testPersonFamiliesDelegatesToReadModelAndPreservesJson(): void
    {
        $client = self::createClient(['debug' => false]);
        $read = $this->createMock(PeopleReadRepository::class);
        $read->expects(self::once())->method('familiesForPerson')->with('person-123')->willReturn([['id' => 'family-123', 'name' => 'Doe', 'role' => 'other', 'membership_created_at' => '2026-10-03', 'created_at' => '2026-10-01', 'updated_at' => '2026-10-02']]);
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
        $read->expects(self::once())->method('profile')->with('person-123')->willReturn(['addresses' => [], 'communications' => [['id' => 'comm-1', 'person_id' => 'person-123', 'channel' => 'email', 'value' => 'jane@example.com', 'preferred' => 1, 'created_at' => '2026-10-03']], 'education' => [], 'employment' => []]);
        self::getContainer()->set(PeopleReadRepository::class, $read);
        self::getContainer()->set(MessageBusInterface::class, $this->createStub(MessageBusInterface::class));
        $client->request('GET', '/api/people/person-123/profile');
        self::assertResponseIsSuccessful();
        self::assertSame(['addresses' => [], 'communicationOptions' => [['id' => 'comm-1', 'personId' => 'person-123', 'channel' => 'email', 'value' => 'jane@example.com', 'preferred' => true, 'createdAt' => '2026-10-03']], 'education' => [], 'employment' => []], json_decode($client->getResponse()->getContent(), true));
    }

    public function testFamilyOverviewIncludesAllHouseholdSections(): void
    {
        $client = self::createClient(['debug' => false]);
        $read = $this->createMock(\App\People\Application\ReadModel\FamilyOverviewRepository::class);
        $member = new \App\People\Application\DTO\FamilyMemberView('person-1', 'Jane', 'Doe', null, 'unspecified', null, null, 'head', '2026-10-01');
        $address = new \App\People\Application\DTO\FamilyOverviewAddressView('address-1', 'residential', '12 Main Road', null, 'Cape Town', null, '8000', 'South Africa');
        $event = new \App\People\Application\DTO\FamilyUpcomingEventView('event-1', 'baptism', '2026-11-01', null, 'active', ['person-1']);
        $view = new \App\People\Application\DTO\FamilyOverviewView('family-123', 'Doe', '2026-10-01', '2026-10-02', [$member], [$address], [$event], $member);
        $read->expects(self::once())->method('overview')->with(null)->willReturn([$view]);
        self::getContainer()->set(\App\People\Application\ReadModel\FamilyOverviewRepository::class, $read);
        self::getContainer()->set(\App\People\Domain\Repository\FamilyRepository::class, $this->createStub(\App\People\Domain\Repository\FamilyRepository::class));
        $client->request('GET', '/api/family-overviews');
        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $response);
        self::assertSame('family-123', $response[0]['id']);
        self::assertSame('Jane', $response[0]['members'][0]['firstName']);
        self::assertSame($response[0]['members'][0], $response[0]['primaryContact']);
        self::assertSame('8000', $response[0]['addresses'][0]['postalCode']);
        self::assertSame(['person-1'], $response[0]['upcomingEvents'][0]['memberIds']);
        $this->assertCamelCaseKeys($response);
    }

    public function testMissingFamilyOverviewReturns404(): void
    {
        $client = self::createClient(['debug' => false]);
        $read = $this->createStub(\App\People\Application\ReadModel\FamilyOverviewRepository::class);
        $read->method('overview')->willReturn([]);
        self::getContainer()->set(\App\People\Application\ReadModel\FamilyOverviewRepository::class, $read);
        self::getContainer()->set(\App\People\Domain\Repository\FamilyRepository::class, $this->createStub(\App\People\Domain\Repository\FamilyRepository::class));
        $client->request('GET', '/api/families/missing/overview');
        self::assertResponseStatusCodeSame(404);
    }

    public function testPrimaryContactMustBelongToFamily(): void
    {
        $client = self::createClient(['debug' => false]);
        $family = \App\People\Domain\Model\Family::create(\App\People\Domain\ValueObject\FamilyId::generate(), 'Doe');
        $repository = $this->createMock(\App\People\Domain\Repository\FamilyRepository::class);
        $repository->method('findById')->willReturn($family);
        $repository->expects(self::never())->method('save');
        self::getContainer()->set(\App\People\Domain\Repository\FamilyRepository::class, $repository);
        self::getContainer()->set(\App\People\Application\ReadModel\FamilyOverviewRepository::class, $this->createStub(\App\People\Application\ReadModel\FamilyOverviewRepository::class));
        $client->jsonRequest('PUT', '/api/families/'.$family->id()->toString().'/primary-contact', ['personId' => PersonId::generate()->toString()]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testPrimaryContactCanBeSelectedAndCleared(): void
    {
        $client = self::createClient(['debug' => false]);
        $family = \App\People\Domain\Model\Family::create(\App\People\Domain\ValueObject\FamilyId::generate(), 'Doe');
        $person = PersonId::generate();
        $family->addMember($person, \App\People\Domain\Enum\FamilyRole::HEAD);
        $repository = $this->createMock(\App\People\Domain\Repository\FamilyRepository::class);
        $repository->method('findById')->willReturn($family);
        $repository->expects(self::exactly(2))->method('save')->with($family);
        self::getContainer()->set(\App\People\Domain\Repository\FamilyRepository::class, $repository);
        self::getContainer()->set(\App\People\Application\ReadModel\FamilyOverviewRepository::class, $this->createStub(\App\People\Application\ReadModel\FamilyOverviewRepository::class));
        $client->disableReboot();
        $url = '/api/families/'.$family->id()->toString().'/primary-contact';
        $client->jsonRequest('PUT', $url, ['personId' => $person->toString()]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame($person->toString(), $family->primaryContact()->toString());
        $client->jsonRequest('PUT', $url, ['personId' => null]);
        self::assertResponseStatusCodeSame(204);
        self::assertNull($family->primaryContact());
    }

    public function testFamilyDetailsSerializeNestedDtosWithCamelCase(): void
    {
        $client = self::createClient(['debug' => false]);
        $read = $this->createMock(PeopleReadRepository::class);
        $read->expects(self::once())->method('family')->with('family-1')->willReturn(['id' => 'family-1', 'name' => 'Doe', 'created_at' => '2026-10-01', 'updated_at' => '2026-10-02', 'primary_contact_person_id' => 'person-1', 'members' => [['id' => 'person-1', 'first_name' => 'Jane', 'last_name' => 'Doe', 'date_of_birth' => null, 'gender' => 'unspecified', 'email' => null, 'phone' => null, 'role' => 'head', 'membership_created_at' => '2026-10-01']], 'addresses' => [['id' => 'address-1', 'family_id' => 'family-1', 'type' => 'residential', 'line1' => '12 Main Road', 'line2' => null, 'city' => 'Cape Town', 'province' => null, 'postal_code' => '8000', 'country' => 'South Africa', 'created_at' => '2026-10-01']]]);
        self::getContainer()->set(PeopleReadRepository::class, $read);
        self::getContainer()->set(MessageBusInterface::class, $this->createStub(MessageBusInterface::class));
        $client->request('GET', '/api/families/family-1');
        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('person-1', $response['primaryContactPersonId']);
        self::assertSame('Jane', $response['members'][0]['firstName']);
        self::assertSame('8000', $response['addresses'][0]['postalCode']);
        self::assertSame('family-1', $response['addresses'][0]['familyId']);
        $this->assertCamelCaseKeys($response);
    }

    public function testRelationshipsIncludeRelatedNamesAndEndDateInCamelCase(): void
    {
        $client = self::createClient(['debug' => false]);
        $read = $this->createMock(PeopleReadRepository::class);
        $read->expects(self::once())->method('relationshipsForPerson')->with('person-1')->willReturn([['id' => 'relationship-1', 'person_id' => 'person-1', 'related_person_id' => 'person-2', 'related_first_name' => 'John', 'related_last_name' => 'Doe', 'type' => 'spouse', 'ended_at' => null, 'created_at' => '2026-10-01', 'updated_at' => '2026-10-02']]);
        self::getContainer()->set(PeopleReadRepository::class, $read);
        self::getContainer()->set(MessageBusInterface::class, $this->createStub(MessageBusInterface::class));
        $client->request('GET', '/api/people/person-1/relationships');
        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('person-2', $response[0]['relatedPersonId']);
        self::assertSame('John', $response[0]['relatedFirstName']);
        self::assertNull($response[0]['endedAt']);
        $this->assertCamelCaseKeys($response);
    }

    public function testProfileHistorySerializesNestedDtosAndPreservesNulls(): void
    {
        $client = self::createClient(['debug' => false]);
        $read = $this->createMock(PeopleReadRepository::class);
        $read->expects(self::once())->method('profile')->with('person-1')->willReturn(['addresses' => [['id' => 'address-1', 'person_id' => 'person-1', 'type' => 'residential', 'line1' => '12 Main Road', 'line2' => null, 'city' => 'Cape Town', 'province' => null, 'postal_code' => '8000', 'country' => 'South Africa', 'created_at' => '2026-10-01']], 'communications' => [], 'education' => [['id' => 'education-1', 'person_id' => 'person-1', 'institution' => 'UCT', 'qualification' => 'BSc', 'start_date' => '2020-01-01', 'end_date' => null, 'field_of_study' => 'Computer Science', 'notes' => null, 'created_at' => '2026-10-01']], 'employment' => [['id' => 'employment-1', 'person_id' => 'person-1', 'employer' => 'Example', 'job_title' => 'Developer', 'start_date' => '2023-01-01', 'end_date' => null, 'industry' => null, 'work_email' => null, 'work_phone' => null, 'notes' => null, 'created_at' => '2026-10-01']]]);
        self::getContainer()->set(PeopleReadRepository::class, $read);
        self::getContainer()->set(MessageBusInterface::class, $this->createStub(MessageBusInterface::class));
        $client->request('GET', '/api/people/person-1/profile');
        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('8000', $response['addresses'][0]['postalCode']);
        self::assertSame('Computer Science', $response['education'][0]['fieldOfStudy']);
        self::assertSame('Developer', $response['employment'][0]['jobTitle']);
        self::assertNull($response['employment'][0]['workEmail']);
        self::assertSame([], $response['communicationOptions']);
        $this->assertCamelCaseKeys($response);
    }

    public function testPersonAutocompleteSearchesTheDatabaseAndBoundsResults(): void
    {
        $client = self::createClient(['debug' => false]);
        $db = \Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE people_persons (id TEXT, first_name TEXT, last_name TEXT, date_of_birth TEXT, email TEXT, phone TEXT)');
        $first = '11111111-1111-4111-8111-111111111111';
        $second = '22222222-2222-4222-8222-222222222222';
        $db->insert('people_persons', ['id' => $first, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.org', 'phone' => '+27 (82) 123-4567']);
        $db->insert('people_persons', ['id' => $second, 'first_name' => 'Jane', 'last_name' => 'Zulu', 'email' => 'other@example.org']);
        $db->insert('people_persons', ['id' => '33333333-3333-4333-8333-333333333333', 'first_name' => '100% Real', 'last_name' => 'Literal']);
        $client->disableReboot();
        self::getContainer()->set(\App\People\Application\ReadModel\PersonSearchReader::class, new \App\People\Infrastructure\ReadModel\DBAL\DbalPersonSearchReader($db));
        $client->request('GET', '/api/people/search', ['q' => 'jane', 'limit' => 1]);
        self::assertResponseIsSuccessful();
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $body['items']);
        self::assertTrue($body['hasMore']);
        self::assertSame($first, $body['items'][0]['id']);
        self::assertArrayNotHasKey('createdAt', $body['items'][0]);
        foreach (['DOE jane', 'jane@example', '821234567'] as $query) {
            $client->request('GET', '/api/people/search', ['q' => $query]);
            self::assertResponseIsSuccessful();
            self::assertSame($first, json_decode($client->getResponse()->getContent(), true)['items'][0]['id']);
        }
        $client->request('GET', '/api/people/search', ['q' => 'Jane', 'excludeIds' => [$first]]);
        self::assertSame($second, json_decode($client->getResponse()->getContent(), true)['items'][0]['id']);
        $client->request('GET', '/api/people/search', ['ids' => [$first, $second]]);
        self::assertCount(2, json_decode($client->getResponse()->getContent(), true)['items']);
        $client->request('GET', '/api/people/search', ['q' => '% Real']);
        self::assertCount(1, json_decode($client->getResponse()->getContent(), true)['items']);
        $client->request('GET', '/api/people/search', ['q' => 'j']);
        self::assertSame(['items' => [], 'hasMore' => false], json_decode($client->getResponse()->getContent(), true));
        $client->request('GET', '/api/people/search', ['q' => "' OR 1=1 --"]);
        self::assertSame([], json_decode($client->getResponse()->getContent(), true)['items']);
    }

    public function testPersonAutocompleteRejectsInvalidAndOversizedRequests(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->disableReboot();
        $read = $this->createMock(\App\People\Application\ReadModel\PersonSearchReader::class);
        $read->expects(self::never())->method('search');
        self::getContainer()->set(\App\People\Application\ReadModel\PersonSearchReader::class, $read);
        foreach ([['limit' => 51], ['q' => str_repeat('x', 121)], ['ids' => ['invalid']], ['excludeIds' => ['invalid']]] as $parameters) {
            $client->request('GET', '/api/people/search', $parameters);
            self::assertResponseStatusCodeSame(422);
        }
    }

    private function assertCamelCaseKeys(array $response): void
    {
        foreach ($response as $key => $value) {
            if (is_string($key)) {
                self::assertStringNotContainsString('_', $key);
            }
            if (is_array($value)) {
                $this->assertCamelCaseKeys($value);
            }
        }
    }

    private function assertCommandRequest(string $path, array $payload, object $expectedCommand, int $status = 201): void
    {
        $client = self::createClient(['debug' => false]);
        $id = PersonId::generate();
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::equalTo($expectedCommand))->willReturn(new Envelope($expectedCommand, [new HandledStamp($id, 'test_handler')]));
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
        $container->extension('framework', ['secret' => 'people-http-tests', 'test' => true, 'router' => ['utf8' => true], 'serializer' => ['enabled' => true], 'validation' => ['enabled' => true]]);
        $services = $container->services()->defaults()->autowire()->autoconfigure();
        \App\Tests\Support\ApplicationPorts::reads($services);
        $services->set(\App\Shared\Infrastructure\Operations\Readiness::class)->synthetic()->public();
        $services->set(MessageBusInterface::class)->synthetic()->public();
        $services->set(\App\People\Application\ReadModel\PersonSearchReader::class)->synthetic()->public();
        $services->set(PeopleReadRepository::class)->synthetic()->public();
        $services->set(\App\People\Application\ReadModel\FamilyOverviewRepository::class)->synthetic()->public();
        $services->set(\App\People\Domain\Repository\FamilyRepository::class)->synthetic()->public();
        $services->set(\App\People\Application\Command\SetFamilyPrimaryContact\SetFamilyPrimaryContactHandler::class);
        $services->set(CommandDispatcher::class);
        $source = dirname((new ReflectionClass(PeopleController::class))->getFileName(), 4);
        $services->load('App\People\Presentation\Http\Controller\\', $source.'/Presentation/Http/Controller/*')->public();
        $services->load('App\People\Application\Query\\', $source.'/Application/Query/*/*Handler.php');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(dirname((new ReflectionClass(PeopleController::class))->getFileName()).'/', 'attribute');
    }
}

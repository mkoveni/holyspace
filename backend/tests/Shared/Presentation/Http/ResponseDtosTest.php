<?php

declare(strict_types=1);

namespace App\Tests\Shared\Presentation\Http;

use App\IdentityAccess\Application\Command\CreateRole\CreateRoleCommand;
use App\IdentityAccess\Application\Query\GetUserAccount\GetUserAccountHandler;
use App\IdentityAccess\Application\Query\GetUserPermissions\GetUserPermissionsHandler;
use App\IdentityAccess\Application\ReadModel\IdentityAccessReadRepository;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;
use App\IdentityAccess\Domain\ValueObject\RoleId;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use App\IdentityAccess\Presentation\Http\Controller\UsersController;
use App\LifeEvents\Domain\Details\GenericLifeEventDetails;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Enum\ParticipantRole;
use App\LifeEvents\Domain\Model\LifeEvent;
use App\LifeEvents\Domain\Model\LifeEventParticipant;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use App\LifeEvents\Domain\ValueObject\PersonId;
use App\LifeEvents\Presentation\Http\Controller\LifeEventController;
use DateTimeImmutable;
use InvalidArgumentException;
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

final class ResponseDtosTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return ResponseDtosTestKernel::class;
    }

    public function testUserResponseKeepsCamelCaseAndRoleIds(): void
    {
        $client = self::createClient(['debug' => false]);
        $account = UserAccount::create(UserId::generate(), Username::fromString('jane'), PasswordHash::fromString('hash'), 'person-1');
        $role = RoleId::generate();
        $account->assignRole($role);
        $users = $this->createMock(UserAccountRepository::class);
        $users->expects(self::once())->method('findById')->willReturn($account);
        self::getContainer()->set(UserAccountRepository::class, $users);
        self::getContainer()->set(IdentityAccessReadRepository::class, $this->createStub(IdentityAccessReadRepository::class));
        self::getContainer()->set(MessageBusInterface::class, $this->createStub(MessageBusInterface::class));
        $client->request('GET', '/api/users/'.$account->id()->toString());
        self::assertResponseIsSuccessful();
        self::assertSame(['id' => $account->id()->toString(), 'username' => 'jane', 'personId' => 'person-1', 'status' => 'active', 'roleIds' => [$role->toString()], 'createdAt' => $account->createdAt()->format(DATE_ATOM), 'updatedAt' => $account->updatedAt()->format(DATE_ATOM)], json_decode($client->getResponse()->getContent(), true));
    }

    public function testLifeEventSerializesParticipantDtosAndCustomDetails(): void
    {
        $client = self::createClient(['debug' => false]);
        $personId = PersonId::fromString('123e4567-e89b-12d3-a456-426614174001');
        $event = LifeEvent::create(LifeEventId::generate(), LifeEventType::BAPTISM, [LifeEventParticipant::create($personId, ParticipantRole::SUBJECT)], new DateTimeImmutable('2026-10-01'), null, new GenericLifeEventDetails(['venue' => 'Church', 'custom_field' => 'Caller-provided metadata']));
        $events = $this->createMock(LifeEventRepository::class);
        $events->expects(self::once())->method('findById')->willReturn($event);
        self::getContainer()->set(LifeEventRepository::class, $events);
        self::getContainer()->set(MessageBusInterface::class, $this->createStub(MessageBusInterface::class));
        $client->request('GET', '/api/life-events/'.$event->id()->toString());
        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([['personId' => $personId->toString(), 'role' => 'subject']], $response['participants']);
        self::assertSame('2026-10-01', $response['eventDate']);
        self::assertSame(['venue' => 'Church', 'custom_field' => 'Caller-provided metadata'], $response['details']);
        self::assertNull($response['notes']);
        self::assertArrayHasKey('createdAt', $response);
    }

    public function testInvalidRolePayloadNeverReachesTheBus(): void
    {
        $client = self::createClient(['debug' => false]);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        self::getContainer()->set(MessageBusInterface::class, $bus);
        $client->jsonRequest('POST', '/api/roles', ['name' => '']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testWrappedDomainErrorsReturnUsefulClientErrors(): void
    {
        $client = self::createClient(['debug' => false]);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willThrowException(new \Symfony\Component\Messenger\Exception\HandlerFailedException(new Envelope(new CreateRoleCommand('Admin')), [new InvalidArgumentException('Role already exists.')]));
        self::getContainer()->set(MessageBusInterface::class, $bus);
        $client->jsonRequest('POST', '/api/roles', ['name' => 'Admin']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Role already exists.', json_decode($client->getResponse()->getContent(), true)['message']);
    }

    public function testOrdinaryAccountsCannotCreateRoles(): void
    {
        $client = self::createClient(['debug' => false]);
        self::getContainer()->get(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class)->allowed = false;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        self::getContainer()->set(MessageBusInterface::class, $bus);
        $client->jsonRequest('POST', '/api/roles', ['name' => 'Administrator', 'description' => 'Escalation attempt']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testCreatedResponsePreservesStatusAndId(): void
    {
        $client = self::createClient(['debug' => false]);
        $id = RoleId::generate();
        $command = new CreateRoleCommand('Admin', 'Administrator');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::equalTo($command))->willReturn(new Envelope($command, [new HandledStamp($id, 'test_handler')]));
        self::getContainer()->set(MessageBusInterface::class, $bus);
        $client->jsonRequest('POST', '/api/roles', ['name' => 'Admin', 'description' => 'Administrator']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['id' => $id->toString()], json_decode($client->getResponse()->getContent(), true));
    }
}
final class ResponseDtosTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/onechurch-response-dto-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/onechurch-response-dto-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'response-dto-tests', 'test' => true, 'router' => ['utf8' => true], 'serializer' => ['enabled' => true]]);
        $services = $container->services()->defaults()->autowire()->autoconfigure();
        \App\Tests\Support\ApplicationPorts::reads($services);
        $services->set(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class, ResponseDtosAuthorization::class)->public();
        $services->set(\Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener::class);
        $services->set(\App\Shared\Infrastructure\Http\DomainExceptionListener::class);
        foreach ([\App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler::class, \App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler::class, \App\IdentityAccess\Application\Query\GetAccountSecurityStatus\GetAccountSecurityStatusHandler::class, \App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedHandler::class, \App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountHandler::class, \App\IdentityAccess\Application\Command\SetupAuthenticator\SetupAuthenticatorHandler::class, \App\IdentityAccess\Application\Command\EnableAuthenticator\EnableAuthenticatorHandler::class, \App\IdentityAccess\Application\Command\VerifyAuthenticator\VerifyAuthenticatorHandler::class, \App\IdentityAccess\Application\Command\RegenerateRecoveryCodes\RegenerateRecoveryCodesHandler::class, \App\IdentityAccess\Application\Command\ChangeOwnPassword\ChangeOwnPasswordHandler::class, \App\IdentityAccess\Application\Command\InvalidateMemberAccess\InvalidateMemberAccessHandler::class, \App\IdentityAccess\Application\Command\DisableAccountAndSessions\DisableAccountAndSessionsHandler::class, \App\IdentityAccess\Application\Command\RecoverAccount\RecoverAccountHandler::class, \App\IdentityAccess\Application\Command\IssueAdministratorRecovery\IssueAdministratorRecoveryHandler::class, MessageBusInterface::class, UserAccountRepository::class, IdentityAccessReadRepository::class, LifeEventRepository::class] as $service) {
            $services->set($service)->synthetic()->public();
        }
        $services->set(GetUserAccountHandler::class);
        $services->set(GetUserPermissionsHandler::class);
        foreach ([UsersController::class, LifeEventController::class] as $controller) {
            $directory = dirname((new ReflectionClass($controller))->getFileName());
            $namespace = substr($controller, 0, strrpos($controller, '\\') + 1);
            $controllers = $services->load($namespace, $directory.'/*')->public();
            if (UsersController::class === $controller) {
                $controllers->exclude([$directory.'/AccountSecurityController.php', $directory.'/MemberAccessController.php']);
            }
        }
        $source = dirname((new ReflectionClass(LifeEventController::class))->getFileName(), 4);
        $services->load('App\LifeEvents\Application\Query\\', $source.'/Application/Query/*/*Handler.php');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        foreach ([UsersController::class, LifeEventController::class] as $controller) {
            $routes->import(dirname((new ReflectionClass($controller))->getFileName()).'/', 'attribute');
        }
    }
}
final class ResponseDtosAuthorization implements \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface
{
    public bool $allowed = true;

    public function isGranted(mixed $attribute, mixed $subject = null, ?\Symfony\Component\Security\Core\Authorization\AccessDecision $accessDecision = null): bool
    {
        return $this->allowed;
    }
}

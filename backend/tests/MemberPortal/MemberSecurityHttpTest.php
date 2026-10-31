<?php

declare(strict_types=1);

namespace App\Tests\MemberPortal;

use App\Events\Application\Port\MemberBookingCancellation as MemberBookings;
use App\Giving\Application\ReadModel\DonorStatements;
use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use App\IdentityAccess\Infrastructure\Persistence\DBAL\DbalUserAccountRepository;
use App\IdentityAccess\Infrastructure\Persistence\DBAL\UserAccountMapper;
use App\IdentityAccess\Infrastructure\Security\AccountTokenListener;
use App\IdentityAccess\Infrastructure\Security\SymfonyPasswordHasher;
use App\IdentityAccess\Infrastructure\Security\SymfonyUser;
use App\People\Application\Port\MemberContactWriter as MemberPersonalRecords;
use App\Shared\Application\WorkflowTransaction;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class MemberSecurityHttpTest extends WebTestCase
{
    private $client;
    private Connection $db;
    private UserAccount $user;
    private const PASSWORD = 'Test-Account-Password-123!';

    protected static function getKernelClass(): string
    {
        return MemberSecurityKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient(['debug' => false]);
        $this->client->disableReboot();
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->db->executeStatement('CREATE TABLE user_accounts(id TEXT PRIMARY KEY, username TEXT UNIQUE, password_hash TEXT, person_id TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
        $this->db->executeStatement('CREATE TABLE user_roles(user_id TEXT, role_id TEXT)');
        $this->db->executeStatement('CREATE TABLE roles(id TEXT PRIMARY KEY,name TEXT UNIQUE,description TEXT,created_at TEXT,updated_at TEXT)');
        $this->db->executeStatement('CREATE TABLE permissions(id TEXT PRIMARY KEY,code TEXT UNIQUE,description TEXT DEFAULT "",created_at TEXT DEFAULT "2026-01-01 00:00:00",updated_at TEXT DEFAULT "2026-01-01 00:00:00")');
        $this->db->executeStatement('CREATE TABLE role_permissions(role_id TEXT,permission_id TEXT)');
        $this->db->insert('permissions', ['id' => Uuid::v7()->toRfc4122(), 'code' => 'member.portal']);
        $this->db->executeStatement('CREATE TABLE account_security(user_id TEXT PRIMARY KEY, secret TEXT, pending_secret TEXT, pending_until INT, last_step INT, recovery_hashes TEXT, recovery_until INT, version INT)');
        $this->db->executeStatement('CREATE TABLE account_security_audit(id TEXT PRIMARY KEY,user_id TEXT,actor_id TEXT,action TEXT,reference TEXT,occurred_at TEXT)');
        self::getContainer()->set(Connection::class, $this->db);
        self::getContainer()->set(ClockInterface::class, new MockClock('2030-01-06T09:00:00Z'));
        $directory = $this->createStub(\App\People\Application\ReadModel\PersonDirectory::class);
        $directory->method('get')->willReturnCallback(fn ($id) => '11111111-1111-4111-8111-111111111111' === $id ? null : ['id' => $id, 'name' => 'Known person']);
        $directory->method('getMany')->willReturnCallback(static function (array $ids) use ($directory): array {
            $people = [];
            foreach ($ids as $id) {
                $person = $directory->get($id);
                if (null !== $person) {
                    $people[$id] = $person;
                }
            }

            return $people;
        });
        self::getContainer()->set(\App\People\Application\ReadModel\PersonDirectory::class, $directory);
        $this->user = UserAccount::create(UserId::generate(), Username::fromString('member'), self::getContainer()->get(PasswordHasher::class)->hash(self::PASSWORD), Uuid::v7()->toRfc4122());
        self::getContainer()->get(UserAccountRepository::class)->save($this->user);
        $storage = new MemberTokenStorage();
        $storage->setToken(new UsernamePasswordToken(new SymfonyUser($this->user), 'api', ['ROLE_USER']));
        self::getContainer()->set(TokenStorageInterface::class, $storage);
        self::getContainer()->set(WorkflowTransaction::class, new class($this->db) implements WorkflowTransaction {
            public function __construct(private Connection $db)
            {
            }

            public function run(callable $operation): mixed
            {
                return $this->db->transactional($operation);
            }
        });
        self::getContainer()->set(AuthorizationCheckerInterface::class, new MemberAuthorization());
        $jwt = $this->createStub(JWTTokenManagerInterface::class);
        $jwt->method('createFromPayload')->willReturn('verified-token');
        self::getContainer()->set(JWTTokenManagerInterface::class, $jwt);
        $people = $this->createStub(MemberPersonalRecords::class);
        $people->method('profile')->willReturnCallback(fn ($id) => ['id' => $id, 'name' => 'Own person']);
        $people->method('families')->willReturn([]);
        self::getContainer()->set(MemberPersonalRecords::class, $people);
        $bookings = $this->createStub(MemberBookings::class);
        $bookings->method('list')->willReturnCallback(fn ($id) => [['personId' => $id]]);
        $bookings->method('cancel')->willReturnCallback(fn ($person, $id, $version) => ['personId' => $person, 'id' => $id, 'version' => $version]);
        self::getContainer()->set(MemberBookings::class, $bookings);
        $giving = $this->createStub(DonorStatements::class);
        $giving->method('statement')->willReturnCallback(fn ($id, $from, $to) => ['donorId' => $id, 'from' => $from, 'to' => $to]);
        self::getContainer()->set(DonorStatements::class, $giving);
    }

    private function request(string $method, string $path, array $body = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, $path, $body);
        self::assertResponseStatusCodeSame($status);

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function enable(): array
    {
        $setup = $this->request('POST', '/api/account-security/setup', ['password' => self::PASSWORD]);
        $otp = TOTP::createFromSecret($setup['secret'], self::getContainer()->get(ClockInterface::class));
        $result = $this->request('POST', '/api/account-security/enable', ['password' => self::PASSWORD, 'code' => $otp->now()]);

        return $result + ['secret' => $setup['secret']];
    }

    public function testAuthenticatorSetupReplayAndSensitivePortalGate(): void
    {
        $this->request('GET', '/api/member', [], 403);
        $this->request('GET', '/api/protected-marker');
        $this->request('POST', '/api/account-security/setup', ['password' => 'wrong'], 409);
        $data = $this->enable();
        self::assertCount(8, $data['recoveryCodes']);
        $this->request('GET', '/api/protected-marker', [], 403);
        self::assertSame('verified-token', $data['token']);
        $state = self::getContainer()->get(\App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler::class)(new \App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery($this->user->id()->toString()));
        self::assertNotSame($data['secret'], $state['secret']);
        self::assertStringNotContainsString($data['recoveryCodes'][0], $state['recovery_hashes']);
        $this->request('GET', '/api/member', [], 403);
        $clock = self::getContainer()->get(ClockInterface::class);
        $otp = TOTP::createFromSecret($data['secret'], $clock);
        $this->request('POST', '/api/account-security/verify', ['code' => $otp->now()], 409);
        $clock->modify('+30 seconds');
        $this->request('POST', '/api/account-security/verify', ['code' => $otp->now()]);
        $clock->modify('+60 seconds');
        // Reset the per-minute attempt bucket.
        self::getContainer()->get(TokenStorageInterface::class)->getToken()->setAttribute('mfa_verified', true);
        $this->request('GET', '/api/protected-marker');
        $workspace = $this->request('GET', '/api/member');
        self::assertSame($this->user->personId(), $workspace['profile']['id']);
        self::assertSame($this->user->personId(), $workspace['registrations'][0]['personId']);
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $statement = $this->request('GET', '/api/member/giving?from=2030-01-01&to=2030-12-31&donorId=someone-else');
        self::assertSame($this->user->personId(), $statement['donorId']);
    }

    public function testRecoveryConsumesAllCodesAndRevokesPreviouslyIssuedTokens(): void
    {
        $data = $this->enable();
        $listener = self::getContainer()->get(AccountTokenListener::class);
        $created = new JWTCreatedEvent(['iat' => time(), 'exp' => time() + 3600, 'username' => 'member', 'mfa_verified' => true], new SymfonyUser($this->user));
        $listener->created($created);
        $payload = $created->getData();
        $valid = new JWTDecodedEvent($payload);
        $listener->decoded($valid);
        self::assertTrue($valid->isValid());
        $this->request('POST', '/api/account-recovery', ['username' => 'unknown', 'code' => $data['recoveryCodes'][0], 'newPassword' => 'Replacement-Password-123!'], 409);
        $this->request('POST', '/api/account-recovery', ['username' => 'member', 'code' => $data['recoveryCodes'][0], 'newPassword' => 'Replacement-Password-123!']);
        $invalid = new JWTDecodedEvent($payload);
        $listener->decoded($invalid);
        self::assertFalse($invalid->isValid());
        $state = self::getContainer()->get(\App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler::class)(new \App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery($this->user->id()->toString()));
        self::assertNull($state['secret']);
        self::assertSame('[]', $state['recovery_hashes']);
        $this->request('POST', '/api/account-recovery', ['username' => 'member', 'code' => $data['recoveryCodes'][0], 'newPassword' => 'Replacement-Password-456!'], 409);
        $this->request('POST', '/api/account-recovery', ['username' => 'member', 'code' => $data['recoveryCodes'][1], 'newPassword' => 'Replacement-Password-456!'], 409);
        $user = self::getContainer()->get(UserAccountRepository::class)->findById($this->user->id());
        self::assertTrue(self::getContainer()->get(PasswordHasher::class)->verify('Replacement-Password-123!', $user->passwordHash()));
    }

    public function testPasswordOnlyTokenIsShortLivedAndLegacyTokensAreRejected(): void
    {
        $this->enable();
        $listener = self::getContainer()->get(AccountTokenListener::class);
        $event = new JWTCreatedEvent(['iat' => 1000, 'exp' => 4600, 'username' => 'member'], new SymfonyUser($this->user));
        $listener->created($event);
        self::assertSame(1300, $event->getData()['exp']);
        self::assertFalse($event->getData()['mfa_verified']);
        $legacy = new JWTDecodedEvent(['username' => 'member']);
        $listener->decoded($legacy);
        self::assertFalse($legacy->isValid());
        $payload = $event->getData();
        self::getContainer()->get(\App\IdentityAccess\Application\Command\DisableAccountAndSessions\DisableAccountAndSessionsHandler::class)(new \App\IdentityAccess\Application\Command\DisableAccountAndSessions\DisableAccountAndSessionsCommand($this->user->id()->toString()));
        $user = self::getContainer()->get(UserAccountRepository::class)->findById($this->user->id());
        $user->enable();
        self::getContainer()->get(UserAccountRepository::class)->save($user);
        $old = new JWTDecodedEvent($payload);
        $listener->decoded($old);
        self::assertFalse($old->isValid());
    }

    public function testPasswordChangeAndRecoveryCodeReplacementRevokeOldCredentials(): void
    {
        $data = $this->enable();
        $listener = self::getContainer()->get(AccountTokenListener::class);
        $event = new JWTCreatedEvent(['username' => 'member', 'mfa_verified' => true], new SymfonyUser($this->user));
        $listener->created($event);
        $payload = $event->getData();
        $this->request('POST', '/api/account-security/recovery-codes', ['password' => self::PASSWORD], 403);
        self::getContainer()->get(TokenStorageInterface::class)->getToken()->setAttribute('mfa_verified', true);
        $replacement = $this->request('POST', '/api/account-security/recovery-codes', ['password' => self::PASSWORD]);
        self::assertNotSame($data['recoveryCodes'], $replacement['recoveryCodes']);
        $old = new JWTDecodedEvent($payload);
        $listener->decoded($old);
        self::assertFalse($old->isValid());
        $this->request('POST', '/api/account-recovery', ['username' => 'member', 'code' => $data['recoveryCodes'][0], 'newPassword' => 'Replacement-Password-123!'], 409);
        $this->request('POST', '/api/account-security/password', ['password' => self::PASSWORD, 'newPassword' => 'Replacement-Password-123!']);
        $loaded = self::getContainer()->get(UserAccountRepository::class)->findById($this->user->id());
        self::assertTrue(self::getContainer()->get(PasswordHasher::class)->verify('Replacement-Password-123!', $loaded->passwordHash()));
        $stale = new JWTDecodedEvent($payload);
        $listener->decoded($stale);
        self::assertFalse($stale->isValid());
    }

    public function testAdministratorRecoveryExpiresAndDoesNotExposeSecretsInAudit(): void
    {
        $destination = sys_get_temp_dir().'/recovery-'.bin2hex(random_bytes(8));
        self::getContainer()->get(\App\IdentityAccess\Application\Command\IssueAdministratorRecovery\IssueAdministratorRecoveryHandler::class)(new \App\IdentityAccess\Application\Command\IssueAdministratorRecovery\IssueAdministratorRecoveryCommand('member', 'Verified identity case 123', $destination));
        $code = trim(file_get_contents($destination));
        unlink($destination);
        self::assertSame(32, strlen($code));
        $clock = self::getContainer()->get(ClockInterface::class);
        $clock->modify('+31 minutes');
        $this->request('POST', '/api/account-recovery', ['username' => 'member', 'code' => $code, 'newPassword' => 'Replacement-Password-123!'], 409);
        $audit = $this->db->fetchAllAssociative('SELECT * FROM account_security_audit');
        self::assertSame('administrator_recovery', $audit[0]['action']);
        self::assertSame('Verified identity case 123', $audit[0]['reference']);
        self::assertStringNotContainsString($code, json_encode($audit));
    }

    public function testMemberScopeCannotBeOverriddenAndPermissionOrLinkIsRequired(): void
    {
        $this->enable();
        $token = self::getContainer()->get(TokenStorageInterface::class)->getToken();
        $token->setAttribute('mfa_verified', true);
        $booking = $this->request('POST', '/api/member/registrations/'.Uuid::v7()->toRfc4122().'/cancel', ['expectedVersion' => 1, 'personId' => 'someone-else']);
        self::assertSame($this->user->personId(), $booking['personId']);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->allowed = false;
        $this->request('GET', '/api/member', [], 403);
        self::getContainer()->get(AuthorizationCheckerInterface::class)->allowed = true;
        $unlinked = UserAccount::create($this->user->id(), $this->user->username(), $this->user->passwordHash(), null);
        $token->setUser(new SymfonyUser($unlinked));
        $this->request('GET', '/api/member', [], 403);
    }

    public function testAdministratorRecoveryCommandCreatesPrivateFileWithoutPrintingCode(): void
    {
        $path = '/tmp/member-recovery-'.bin2hex(random_bytes(6));
        try {
            $tester = new \Symfony\Component\Console\Tester\CommandTester(new \App\IdentityAccess\Infrastructure\Console\RecoverMemberAccountCommand(self::getContainer()->get(\App\IdentityAccess\Application\Command\IssueAdministratorRecovery\IssueAdministratorRecoveryHandler::class)));
            self::assertSame(0, $tester->execute(['--username' => 'member', '--identity-reference' => 'verified-456', '--code-file' => $path]));
            $code = trim(file_get_contents($path));
            self::assertSame(32, strlen($code));
            self::assertSame(0600, fileperms($path) & 0777);
            self::assertStringNotContainsString($code, $tester->getDisplay());
            $version = self::getContainer()->get(\App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler::class)(new \App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery($this->user->id()->toString()))['version'];
            self::assertSame(1, $tester->execute(['--username' => 'member', '--identity-reference' => 'verified-456', '--code-file' => $path]));
            self::assertSame($version, self::getContainer()->get(\App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler::class)(new \App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery($this->user->id()->toString()))['version']);
            $this->request('POST', '/api/account-recovery', ['username' => 'member', 'code' => $code, 'newPassword' => 'Replacement-Password-123!']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testAdministratorCanEnableExistingMemberAndRelinkingRevokesSessions(): void
    {
        $data = $this->request('GET', '/api/users?search=member');
        self::assertSame('member', $data['items'][0]['username']);
        self::assertArrayNotHasKey('password_hash', $data['items'][0]);
        $path = '/api/users/'.$this->user->id()->toString().'/member-access';
        $this->request('POST', $path, ['personId' => $this->user->personId(), 'expectedPersonId' => null], 409);
        $this->request('POST', $path, ['personId' => '11111111-1111-4111-8111-111111111111', 'expectedPersonId' => $this->user->personId()], 404);
        self::assertSame(0, $this->db->fetchOne('SELECT COUNT(*) FROM user_roles'));
        $granted = $this->request('POST', $path, ['personId' => $this->user->personId(), 'expectedPersonId' => $this->user->personId()]);
        self::assertSame($this->user->personId(), $granted['personId']);
        self::assertSame(['member.portal'], $this->db->fetchFirstColumn('SELECT p.code FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=?', [$this->user->id()->toString()]));
        $version = self::getContainer()->get(\App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler::class)(new \App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery($this->user->id()->toString()))['version'];
        $this->request('POST', $path, ['personId' => $this->user->personId(), 'expectedPersonId' => $this->user->personId()]);
        self::assertSame($version, self::getContainer()->get(\App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler::class)(new \App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery($this->user->id()->toString()))['version']);
        $person = Uuid::v7()->toRfc4122();
        $this->request('POST', $path, ['personId' => $person, 'expectedPersonId' => $this->user->personId()]);
        self::assertSame($person, self::getContainer()->get(UserAccountRepository::class)->findById($this->user->id())->personId());
        self::assertSame($version + 1, self::getContainer()->get(\App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler::class)(new \App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateQuery($this->user->id()->toString()))['version']);
        self::assertSame('member_access_changed', $this->db->fetchOne('SELECT action FROM account_security_audit LIMIT 1'));
    }

    public function testOrdinaryAccountCannotListOrProvisionMemberAccess(): void
    {
        self::getContainer()->get(AuthorizationCheckerInterface::class)->allowed = false;
        $this->request('GET', '/api/users', [], 403);
        $this->request('POST', '/api/users/'.$this->user->id()->toString().'/member-access', ['personId' => $this->user->personId(), 'expectedPersonId' => $this->user->personId()], 403);
        self::assertSame(0, $this->db->fetchOne('SELECT COUNT(*) FROM user_roles'));
    }

    public function testExpiredPendingSetupCannotEnableAndRecoveryIsThrottled(): void
    {
        $setup = $this->request('POST', '/api/account-security/setup', ['password' => self::PASSWORD]);
        $clock = self::getContainer()->get(ClockInterface::class);
        $clock->modify('+11 minutes');
        $this->request('POST', '/api/account-security/enable', ['password' => self::PASSWORD, 'code' => TOTP::createFromSecret($setup['secret'], $clock)->now()], 409);
        for ($i = 0; $i < 5; ++$i) {
            $this->request('POST', '/api/account-recovery', ['username' => 'member', 'code' => 'invalid', 'newPassword' => 'Replacement-Password-123!'], 409);
        }
        $this->request('POST', '/api/account-recovery', ['username' => 'member', 'code' => 'invalid', 'newPassword' => 'Replacement-Password-123!'], 429);
    }
}
final class MemberAuthorization implements AuthorizationCheckerInterface
{
    public bool $allowed = true;

    public function isGranted(mixed $attribute, mixed $subject = null, ?\Symfony\Component\Security\Core\Authorization\AccessDecision $accessDecision = null): bool
    {
        return $this->allowed;
    }
}
final class MemberProtectedController
{
    public function __invoke(): \Symfony\Component\HttpFoundation\JsonResponse
    {
        return new \Symfony\Component\HttpFoundation\JsonResponse(['protected' => true]);
    }
}
final class MemberTokenStorage implements TokenStorageInterface
{
    private ?TokenInterface $token = null;

    public function getToken(): ?TokenInterface
    {
        return $this->token;
    }

    public function setToken(?TokenInterface $token): void
    {
        $this->token = $token;
    }
}
final class MemberSecurityKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return '/tmp/onechurch-member-tests/cache';
    }

    public function getLogDir(): string
    {
        return '/tmp/onechurch-member-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'member-security-tests', 'exceptions' => [AccessDeniedException::class => ['status_code' => 403]], 'test' => true, 'router' => ['utf8' => true], 'serializer' => ['enabled' => true], 'validation' => ['enabled' => true], 'rate_limiter' => ['account_security' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'], 'account_recovery' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '15 minutes']]]);
        $services = $container->services()->defaults()->autowire()->autoconfigure()->public();
        \App\Tests\Support\ApplicationPorts::reads($services);
        foreach ([\App\IdentityAccess\Domain\Repository\RoleRepository::class => \App\IdentityAccess\Infrastructure\Persistence\DBAL\DbalRoleRepository::class, \App\IdentityAccess\Domain\Repository\PermissionRepository::class => \App\IdentityAccess\Infrastructure\Persistence\DBAL\DbalPermissionRepository::class] as $port => $adapter) {
            $services->set($adapter);
            $services->alias($port, $adapter);
        }
        $services->set(\App\IdentityAccess\Infrastructure\Persistence\DBAL\RoleMapper::class);
        $services->set(\App\IdentityAccess\Infrastructure\Persistence\DBAL\PermissionMapper::class);
        $services->set(\App\Shared\Infrastructure\Http\DomainExceptionListener::class);
        \App\Tests\Support\ApplicationPorts::identity($services);
        $services->alias(\App\Events\Application\ReadModel\MemberBookings::class, MemberBookings::class);
        $services->alias(\App\People\Application\ReadModel\MemberPersonalRecords::class, MemberPersonalRecords::class);
        foreach ([Connection::class, ClockInterface::class, WorkflowTransaction::class, TokenStorageInterface::class, AuthorizationCheckerInterface::class, JWTTokenManagerInterface::class, MemberPersonalRecords::class, MemberBookings::class, DonorStatements::class, \App\People\Application\ReadModel\PersonDirectory::class] as $class) {
            $services->set($class)->synthetic()->public();
        }
        foreach ([\App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler::class, \App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler::class, \App\IdentityAccess\Application\Query\GetAccountSecurityStatus\GetAccountSecurityStatusHandler::class, \App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedHandler::class, \App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountHandler::class, \App\IdentityAccess\Application\Command\SetupAuthenticator\SetupAuthenticatorHandler::class, \App\IdentityAccess\Application\Command\EnableAuthenticator\EnableAuthenticatorHandler::class, \App\IdentityAccess\Application\Command\VerifyAuthenticator\VerifyAuthenticatorHandler::class, \App\IdentityAccess\Application\Command\RegenerateRecoveryCodes\RegenerateRecoveryCodesHandler::class, \App\IdentityAccess\Application\Command\ChangeOwnPassword\ChangeOwnPasswordHandler::class, \App\IdentityAccess\Application\Command\InvalidateMemberAccess\InvalidateMemberAccessHandler::class, \App\IdentityAccess\Application\Command\DisableAccountAndSessions\DisableAccountAndSessionsHandler::class, \App\IdentityAccess\Application\Command\RecoverAccount\RecoverAccountHandler::class, \App\IdentityAccess\Application\Command\IssueAdministratorRecovery\IssueAdministratorRecoveryHandler::class, AccountTokenListener::class, \App\IdentityAccess\Application\Command\EnableMemberAccess\EnableMemberAccessHandler::class, \App\IdentityAccess\Infrastructure\ReadModel\DBAL\DbalAccessCatalogue::class, \App\IdentityAccess\Presentation\Http\Controller\MemberAccessController::class, \App\IdentityAccess\Infrastructure\Http\MfaAccessListener::class, MemberProtectedController::class, \App\MemberPortal\Application\Query\GetMemberWorkspace\GetMemberWorkspaceHandler::class, \App\MemberPortal\Application\Command\UpdateMemberContact\UpdateMemberContactHandler::class, \App\MemberPortal\Application\Command\CancelMemberBooking\CancelMemberBookingHandler::class, \App\MemberPortal\Application\Query\GetMemberStatement\GetMemberStatementHandler::class, DbalUserAccountRepository::class, UserAccountMapper::class, SymfonyPasswordHasher::class, \App\IdentityAccess\Presentation\Http\Controller\AccountSecurityController::class, \App\MemberPortal\Presentation\Http\MemberPortalController::class, \Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener::class] as $class) {
            $services->set($class);
        }
        $services->set(\Symfony\Component\RateLimiter\Storage\InMemoryStorage::class);
        foreach (['account_security' => '1 minute', 'account_recovery' => '15 minutes'] as $name => $interval) {
            $services->set('limiter.'.$name, \Symfony\Component\RateLimiter\RateLimiterFactory::class)->args([['id' => $name, 'policy' => 'fixed_window', 'limit' => 5, 'interval' => $interval], \Symfony\Component\DependencyInjection\Loader\Configurator\service(\Symfony\Component\RateLimiter\Storage\InMemoryStorage::class)]);
        }
        $services->alias(\App\IdentityAccess\Application\ReadModel\AccessCatalogue::class, \App\IdentityAccess\Infrastructure\ReadModel\DBAL\DbalAccessCatalogue::class)->public();
        $services->alias(UserAccountRepository::class, DbalUserAccountRepository::class)->public();
        $services->alias(PasswordHasher::class, SymfonyPasswordHasher::class)->public();
        $services->alias('security.authorization_checker', AuthorizationCheckerInterface::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('member_protected_marker', '/api/protected-marker')->controller(MemberProtectedController::class);
        $base = dirname(__DIR__, 2).'/src';
        $routes->import($base.'/IdentityAccess/Presentation/Http/Controller/AccountSecurityController.php', 'attribute');
        $routes->import($base.'/IdentityAccess/Presentation/Http/Controller/MemberAccessController.php', 'attribute');
        $routes->import($base.'/MemberPortal/Presentation/Http/MemberPortalController.php', 'attribute');
    }
}

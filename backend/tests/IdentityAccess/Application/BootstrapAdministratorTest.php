<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Application;

use App\IdentityAccess\Infrastructure\Console\BootstrapAdministratorCommand;
use App\IdentityAccess\Infrastructure\Security\SymfonyPasswordHasher;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class BootstrapAdministratorTest extends TestCase
{
    public function testCreatesOnlyTheFirstAdministratorWithoutPrintingThePassword(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach (['user_accounts(id TEXT, username TEXT, password_hash TEXT, person_id TEXT, status TEXT, created_at TEXT, updated_at TEXT)', 'roles(id TEXT, name TEXT, description TEXT, created_at TEXT, updated_at TEXT)', 'permissions(id TEXT, code TEXT)', 'role_permissions(role_id TEXT, permission_id TEXT)', 'user_roles(user_id TEXT, role_id TEXT)'] as $table) {
            $db->executeStatement('CREATE TABLE '.$table);
        }
        foreach (['identity.manage', 'people.manage', 'families.manage', 'events.manage', 'life_events.manage'] as $index => $code) {
            $db->insert('permissions', ['id' => (string) $index, 'code' => $code]);
        }
        $path = tempnam(sys_get_temp_dir(), 'church-password-');
        file_put_contents($path, 'Private-Password-123!');
        try {
            $tester = new CommandTester(new BootstrapAdministratorCommand($db, new SymfonyPasswordHasher(), new LockFactory(new FlockStore())));
            $arguments = ['--username' => 'administrator', '--password-file' => $path];
            self::assertSame(0, $tester->execute($arguments));
            self::assertStringNotContainsString('Private-Password', $tester->getDisplay());
            self::assertSame(5, (int) $db->fetchOne('SELECT COUNT(*) FROM role_permissions'));
            self::assertTrue(password_verify('Private-Password-123!', $db->fetchOne('SELECT password_hash FROM user_accounts')));
            self::assertSame(1, $tester->execute($arguments));
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM user_accounts'));
        } finally {
            unlink($path);
        }
    }
}

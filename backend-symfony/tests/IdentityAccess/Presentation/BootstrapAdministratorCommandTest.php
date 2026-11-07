<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class BootstrapAdministratorCommandTest extends IdentityApiTestCase
{
    private string $passwordFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordFile = (string) tempnam(sys_get_temp_dir(), 'pw');
        file_put_contents($this->passwordFile, "first administrator password\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->passwordFile);
        parent::tearDown();
    }

    public function testCreatesTheFirstAdministratorOnceAndTheyCanSignIn(): void
    {
        $first = $this->bootstrap(['--username' => 'root', '--email' => 'root@example.test', '--display-name' => 'Root']);
        self::assertSame(0, $first->getStatusCode(), $first->getDisplay());
        self::assertSame(1, $this->rows('user_roles', "role_id = '".self::ADMIN_ROLE."'"));
        self::assertSame(1, $this->rows('identity_access_audit', "action = 'administrator.bootstrapped' AND actor_id IS NULL"));

        $this->call('POST', '/api/login_check', null, ['username' => 'root', 'password' => 'first administrator password']);
        self::assertSame(200, $this->code());

        $second = $this->bootstrap(['--username' => 'again', '--email' => 'again@example.test', '--display-name' => 'Again']);
        self::assertSame(1, $second->getStatusCode());
        self::assertStringContainsString('only allowed before the first account exists', (string) preg_replace('/\s+/', ' ', $second->getDisplay()));
        self::assertSame(1, $this->rows('user_accounts'));
    }

    public function testRequiresAPasswordFileAndAValidPassword(): void
    {
        $tester = new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find('app:identity:bootstrap-admin'));
        $tester->execute(['--username' => 'root', '--email' => 'root@example.test', '--display-name' => 'Root']);
        self::assertSame(1, $tester->getStatusCode());

        file_put_contents($this->passwordFile, 'short');
        $weak = $this->bootstrap(['--username' => 'root', '--email' => 'root@example.test', '--display-name' => 'Root']);
        self::assertSame(1, $weak->getStatusCode());
        self::assertSame(0, $this->rows('user_accounts'));
    }

    /** @param array<string, string> $options */
    private function bootstrap(array $options): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find('app:identity:bootstrap-admin'));
        $tester->execute($options + ['--password-file' => $this->passwordFile]);

        return $tester;
    }
}

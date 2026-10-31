<?php

declare(strict_types=1);
$project = getenv('BROWSER_TEST_PROJECT_DIR') ?: dirname(__DIR__, 2);
require $project.'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv($project.'/.env');
if ('1' !== getenv('BROWSER_TEST_FIXTURE')) {
    throw new RuntimeException('Browser fixtures must be explicitly enabled.');
}
$kernel = new App\Kernel($_SERVER['APP_ENV'] ?? 'dev', false);
$kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
if (!preg_match('/^church_(browser|ci)(?:_test)?$/', (string) $db->fetchOne('SELECT DATABASE()'))) {
    throw new RuntimeException('Fixtures only support a dedicated church_browser or church_ci database.');
}
$db->transactional(function () use ($db): void {
    foreach (['admin', 'reader'] as $kind) {
        $username = 'pilot.'.$kind;
        if ($db->fetchOne('SELECT id FROM user_accounts WHERE username = ?', [$username])) {
            continue;
        }
        $user = Symfony\Component\Uid\Uuid::v7()->toRfc4122();
        $role = Symfony\Component\Uid\Uuid::v7()->toRfc4122();
        $stamp = gmdate('Y-m-d H:i:s');
        $db->insert('roles', ['id' => $role, 'name' => 'browser-'.$kind, 'description' => 'Isolated browser test fixture', 'created_at' => $stamp, 'updated_at' => $stamp]);
        $db->insert('user_accounts', ['id' => $user, 'username' => $username, 'password_hash' => password_hash('Browser-Test-Password-123!', PASSWORD_BCRYPT), 'person_id' => null, 'status' => 'active', 'created_at' => $stamp, 'updated_at' => $stamp]);
        $db->insert('user_roles', ['user_id' => $user, 'role_id' => $role]);
        foreach ($db->fetchAllAssociative('SELECT id, code FROM permissions') as $permission) {
            if ('admin' === $kind || str_ends_with($permission['code'], '.read')) {
                $db->insert('role_permissions', ['role_id' => $role, 'permission_id' => $permission['id']]);
            }
        }
    }
});
echo "Isolated browser accounts ready.\n";

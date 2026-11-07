<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Uid\Uuid;

/** HTTP-level helpers: seeded accounts are inserted directly, everything else goes through the API. */
abstract class IdentityApiTestCase extends DatabaseTestCase
{
    protected const string PASSWORD = 'correct horse battery';
    protected const string ADMIN_ROLE = '00000000-0000-0000-0000-000000000001';

    /** Inserts an account (optionally an administrator) and returns its id. */
    protected function account(string $username, bool $administrator = false, string $status = 'active', string $password = self::PASSWORD): string
    {
        $id = Uuid::v4()->toRfc4122();
        $this->connection->insert('user_accounts', [
            'id' => $id,
            'username' => $username,
            'email' => $username.'@example.test',
            'display_name' => ucfirst($username),
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
            'status' => $status,
            'created_at' => '2026-01-01 00:00:00+00',
            'updated_at' => '2026-01-01 00:00:00+00',
        ]);
        if ($administrator) {
            $this->connection->insert('user_roles', ['user_id' => $id, 'role_id' => self::ADMIN_ROLE]);
        }

        return $id;
    }

    /**
     * Creates an account holding exactly the given permission codes through a dedicated role, and returns its id.
     *
     * @param list<string> $permissions
     */
    protected function accountWith(string $username, array $permissions): string
    {
        $id = $this->account($username);
        $role = Uuid::v4()->toRfc4122();
        $this->connection->insert('roles', ['id' => $role, 'name' => 'test-'.$username]);
        $this->connection->insert('user_roles', ['user_id' => $id, 'role_id' => $role]);
        foreach ($permissions as $code) {
            $this->connection->executeStatement(
                'INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE code = ?',
                [$role, $code],
            );
        }

        return $id;
    }

    protected function login(string $username, string $password = self::PASSWORD): string
    {
        $this->call('POST', '/api/login_check', null, ['username' => $username, 'password' => $password]);
        self::assertResponseStatusCodeSame(200);

        return (string) $this->json()['token'];
    }

    /** @param array<string, mixed>|null $body */
    protected function call(string $method, string $uri, ?string $token = null, ?array $body = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request($method, $uri, server: $server, content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR));
    }

    /** @return array<string|int, mixed> */
    protected function json(): array
    {
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    protected function code(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    protected function message(): string
    {
        return (string) ($this->json()['message'] ?? '');
    }

    protected function rows(string $table, string $where = 'TRUE'): int
    {
        return (int) $this->connection->fetchOne("SELECT count(*) FROM $table WHERE $where");
    }
}

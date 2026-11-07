<?php

declare(strict_types=1);

namespace App\Tests\People\Presentation;

use App\Tests\Support\IdentityApiTestCase;

/** Shared helpers: an administrator (people.manage) and a reader (people.read only). */
abstract class PeopleApiTestCase extends IdentityApiTestCase
{
    protected string $adminId;
    protected string $admin;
    protected string $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');

        $readerId = $this->account('reader');
        $this->connection->insert('roles', ['id' => '00000000-0000-0000-0000-0000000000f1', 'name' => 'people-reader']);
        $this->connection->insert('role_permissions', ['role_id' => '00000000-0000-0000-0000-0000000000f1', 'permission_id' => '00000000-0000-0000-0000-000000000003']);
        $this->connection->insert('user_roles', ['user_id' => $readerId, 'role_id' => '00000000-0000-0000-0000-0000000000f1']);
        $this->reader = $this->login('reader');
    }

    /** @param array<string, mixed> $person */
    protected function register(string $firstName, array $person = [], ?string $membership = null): string
    {
        $body = ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa'] + $person];
        if (null !== $membership) {
            $body['membershipType'] = $membership;
        }
        $this->call('POST', '/api/people', $this->admin, $body);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    protected function version(string $table, string $id): int
    {
        return (int) $this->connection->fetchOne("SELECT version FROM $table WHERE id = ?", [$id]);
    }

    /** @return array<string|int, mixed> */
    protected function person(string $id): array
    {
        $this->call('GET', "/api/people/$id", $this->admin);
        self::assertSame(200, $this->code());

        return $this->json();
    }
}

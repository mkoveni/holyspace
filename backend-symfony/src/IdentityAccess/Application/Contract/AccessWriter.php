<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

interface AccessWriter
{
    /** Serialises every change that could remove the last account manager. */
    public function lockAccessChanges(): void;

    public function createRole(string $id, string $name, string $description): void;

    public function createPermission(string $id, string $code, string $description): void;

    public function assignRole(string $accountId, string $roleId, bool $assign): void;

    public function assignPermission(string $roleId, string $permissionId, bool $assign): void;
}

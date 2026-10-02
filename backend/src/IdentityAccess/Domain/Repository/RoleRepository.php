<?php

declare(strict_types=1);
namespace App\IdentityAccess\Domain\Repository;
use App\IdentityAccess\Domain\Model\Role;
use App\IdentityAccess\Domain\ValueObject\RoleId;
interface RoleRepository
{
    public function save(Role $role): void;
    public function findById(RoleId $id): ?Role;
    public function findByName(string $name): ?Role;
}

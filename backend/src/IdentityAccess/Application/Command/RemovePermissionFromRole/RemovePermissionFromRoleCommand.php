<?php
declare(strict_types=1); namespace App\IdentityAccess\Application\Command\RemovePermissionFromRole; final readonly class RemovePermissionFromRoleCommand { public function __construct(public string $roleId, public string $permissionId) {} }

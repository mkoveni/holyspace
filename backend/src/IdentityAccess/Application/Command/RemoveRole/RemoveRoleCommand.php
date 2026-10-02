<?php

declare(strict_types=1);
namespace App\IdentityAccess\Application\Command\RemoveRole;
final readonly class RemoveRoleCommand { public function __construct(public string $userId, public string $roleId) {} }

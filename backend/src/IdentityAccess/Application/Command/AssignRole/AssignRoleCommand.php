<?php

declare(strict_types=1);
namespace App\IdentityAccess\Application\Command\AssignRole;
final readonly class AssignRoleCommand { public function __construct(public string $userId, public string $roleId) {} }

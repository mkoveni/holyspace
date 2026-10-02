<?php

declare(strict_types=1);
namespace App\IdentityAccess\Application\Query\GetUserPermissions;
final readonly class GetUserPermissionsQuery { public function __construct(public string $userId) {} }

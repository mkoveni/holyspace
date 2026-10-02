<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Enum;

enum PermissionAction: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case RECORD = 'record';
    case MANAGE = 'manage';
    case EXPORT = 'export';
}

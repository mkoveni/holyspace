<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation;

use App\IdentityAccess\Application\AccountView;
use App\IdentityAccess\Application\Contract\PermissionSummary;
use App\IdentityAccess\Application\Contract\RoleSummary;
use App\Shared\Presentation\Json;

/** Wire format of identity resources, matching the Java API's JSON field names. */
final class AccountJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function account(AccountView $view): array
    {
        return [
            'id' => $view->id,
            'username' => $view->username,
            'email' => $view->email,
            'displayName' => $view->displayName,
            'personId' => $view->personId,
            'status' => $view->status,
            'roles' => $view->roles,
            'permissions' => $view->permissions,
            'version' => $view->version,
            'createdAt' => Json::instant($view->createdAt),
            'updatedAt' => Json::instant($view->updatedAt),
        ];
    }

    /** @return array<string, string> */
    public static function role(RoleSummary $role): array
    {
        return ['id' => $role->id, 'name' => $role->name, 'description' => $role->description];
    }

    /** @return array<string, string> */
    public static function permission(PermissionSummary $permission): array
    {
        return ['id' => $permission->id, 'code' => $permission->code, 'description' => $permission->description];
    }
}

<?php

declare(strict_types=1);

namespace App\Forms\Application;

use App\IdentityAccess\Application\Contract\PermissionChecker;

/** Forms permissions: managing implies reading, and reading responses is its own grant. */
final readonly class FormAccess
{
    public function __construct(private PermissionChecker $permissions)
    {
    }

    public function read(): void
    {
        if (!$this->permissions->hasPermission('forms.manage')) {
            $this->permissions->requirePermission('forms.read');
        }
    }

    public function manage(string $actorId): void
    {
        $this->permissions->requireActorPermission($actorId, 'forms.manage');
    }

    /** Aggregates and leaderboards for the current caller. */
    public function viewResponses(): void
    {
        $this->permissions->requirePermission('forms.responses.read');
    }

    public function readResponses(string $actorId): void
    {
        $this->permissions->requireActorPermission($actorId, 'forms.responses.read');
    }

    public function moderate(string $actorId): void
    {
        $this->permissions->requireActorPermission($actorId, 'forms.responses.moderate');
    }
}

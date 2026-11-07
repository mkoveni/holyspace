<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetWorker;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetWorkerHandler
{
    public function __construct(private PermissionChecker $authorization, private SessionReader $sessions)
    {
    }

    public function handle(GetWorkerQuery $query): Worker
    {
        $this->authorization->requirePermission('children.safeguarding');

        return $this->sessions->worker($query->personId) ?? throw ChildrenException::missing('Worker');
    }
}

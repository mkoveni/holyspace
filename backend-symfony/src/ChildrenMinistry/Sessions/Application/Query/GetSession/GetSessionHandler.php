<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetSession;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\SessionOperations;
use App\ChildrenMinistry\Sessions\Application\SessionView;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use Psr\Clock\ClockInterface;

final readonly class GetSessionHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private SessionReader $sessions,
        private SessionOperations $operations,
        private ClockInterface $clock,
    ) {
    }

    public function handle(GetSessionQuery $query): SessionView
    {
        $this->authorization->requirePermission('children.read');

        return $this->operations->view($this->sessions->ministrySession($query->sessionId) ?? throw ChildrenException::missing('MinistrySession'), $this->clock->now());
    }
}

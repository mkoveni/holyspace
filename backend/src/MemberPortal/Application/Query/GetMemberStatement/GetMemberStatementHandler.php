<?php

declare(strict_types=1);

namespace App\MemberPortal\Application\Query\GetMemberStatement;

use App\Giving\Application\ReadModel\DonorStatements;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountQuery;
use App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountHandler;
use App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountQuery;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;

final readonly class GetMemberStatementHandler
{
    public function __construct(
        private AccessControl $access,
        private DonorStatements $giving,
        private RequireVerifiedAccountHandler $requireVerifiedAccountHandler,
        private GetAuthenticatedAccountHandler $getAuthenticatedAccountHandler,
    ) {
    }

    public function __invoke(GetMemberStatementQuery $message): array
    {
        $from = $message->from;
        $to = $message->to;

        return $this->giving->statement($this->personId(), $from, $to);
    }

    private function personId(): string
    {
        if (!$this->access->isGranted('member.portal')) {
            throw new AccessDenied('Member portal permission is required.');
        }
        ($this->requireVerifiedAccountHandler)(new RequireVerifiedAccountQuery());

        return ($this->getAuthenticatedAccountHandler)(new GetAuthenticatedAccountQuery())->personId() ?? throw new AccessDenied('Staff must link your account to a person.');
    }
}

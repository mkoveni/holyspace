<?php

declare(strict_types=1);

namespace App\MemberPortal\Application\Query\GetMemberWorkspace;

use App\Events\Application\ReadModel\MemberBookings;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountQuery;
use App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountHandler;
use App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountQuery;
use App\People\Application\ReadModel\MemberPersonalRecords;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;

final readonly class GetMemberWorkspaceHandler
{
    public function __construct(
        private AccessControl $access,
        private MemberPersonalRecords $people,
        private MemberBookings $bookings,
        private RequireVerifiedAccountHandler $requireVerifiedAccountHandler,
        private GetAuthenticatedAccountHandler $getAuthenticatedAccountHandler,
    ) {
    }

    public function __invoke(GetMemberWorkspaceQuery $message): array
    {
        $id = $this->personId();

        return ['profile' => $this->people->profile($id), 'families' => $this->people->families($id), 'registrations' => $this->bookings->list($id)];
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

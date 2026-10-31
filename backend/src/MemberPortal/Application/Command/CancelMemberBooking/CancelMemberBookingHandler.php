<?php

declare(strict_types=1);

namespace App\MemberPortal\Application\Command\CancelMemberBooking;

use App\Events\Application\Port\MemberBookingCancellation;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler;
use App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountQuery;
use App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountHandler;
use App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountQuery;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CancelMemberBookingHandler
{
    public function __construct(
        private AccessControl $access,
        private MemberBookingCancellation $bookings,
        private WorkflowTransaction $transactions,
        private RequireVerifiedAccountHandler $requireVerifiedAccountHandler,
        private GetAuthenticatedAccountHandler $getAuthenticatedAccountHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CancelMemberBookingCommand $message): array
    {
        $id = $message->id;
        $version = $message->version;

        return $this->transactions->run(fn () => $this->bookings->cancel($this->personId(), $id, $version));
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

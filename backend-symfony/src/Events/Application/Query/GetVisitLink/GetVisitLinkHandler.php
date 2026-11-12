<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetVisitLink;

use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\VisitLinkReader;
use App\Events\Application\Contract\VisitLinkView;
use App\Events\Application\Contract\VisitMessagingReader;
use App\Events\Application\Contract\VisitTokens;
use App\Events\Domain\EventException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** The secret is only shown to staff who may manage attendance, because anyone holding it can check people in. */
final readonly class GetVisitLinkHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private OccurrenceReader $occurrences,
        private VisitLinkReader $links,
        private VisitTokens $tokens,
        private VisitMessagingReader $messaging,
    ) {
    }

    public function handle(GetVisitLinkQuery $query): VisitLinkView
    {
        $this->authorization->requirePermission('events.attendance.manage');
        $occurrence = $this->occurrences->find($query->occurrenceId) ?? throw EventException::missing('Occurrence');
        $state = $this->links->state($occurrence->id);
        $messages = null !== $this->messaging->approver($occurrence->id);
        if (null === $state || !$state['enabled']) {
            return new VisitLinkView($occurrence->id, false, null, $messages);
        }

        return new VisitLinkView($occurrence->id, true, $this->tokens->issue($occurrence->id, $state['generation']), $messages);
    }
}

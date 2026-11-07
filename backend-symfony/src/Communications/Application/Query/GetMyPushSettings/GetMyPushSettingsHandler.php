<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\GetMyPushSettings;

use App\Communications\Application\Contract\MemberPushSettings;
use App\Communications\Application\Contract\PushReader;
use App\Communications\Application\Contract\PushSettings;
use App\Communications\Domain\CommunicationException;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\IdentityAccess\Application\Contract\CurrentActor;

/** The caller's push setup: read-only, so viewing it never creates a registration or a job. */
final readonly class GetMyPushSettingsHandler
{
    public function __construct(
        private AuthenticatedAccount $account,
        private PushReader $reader,
        private PushSettings $settings,
        private CurrentActor $actor,
    ) {
    }

    public function handle(GetMyPushSettingsQuery $query): MemberPushSettings
    {
        $caller = $this->account->requireAccount();
        $session = $this->actor->current()->sessionId ?? throw CommunicationException::forbidden();

        return new MemberPushSettings(
            $this->settings->enabled(),
            $this->settings->projectId(),
            $this->reader->preferences($caller),
            $this->reader->devices($caller, $session),
        );
    }
}

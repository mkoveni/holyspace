<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetMyEvent;

use App\Events\Application\Contract\MemberEventItem;
use App\Events\Application\Contract\MemberEventReader;
use App\Events\Domain\EventException;
use App\IdentityAccess\Application\Contract\MemberIdentity;

final readonly class GetMyEventHandler
{
    public function __construct(private MemberIdentity $identity, private MemberEventReader $reader)
    {
    }

    public function handle(GetMyEventQuery $query): MemberEventItem
    {
        $person = $this->identity->currentPerson() ?? throw EventException::missing('Member event');

        return $this->reader->detail($person, $query->occurrenceId) ?? throw EventException::missing('Member event');
    }
}

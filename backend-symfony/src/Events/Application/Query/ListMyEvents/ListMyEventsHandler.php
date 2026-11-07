<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListMyEvents;

use App\Events\Application\Contract\MemberEventPage;
use App\Events\Application\Contract\MemberEventReader;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\MemberIdentity;
use Psr\Clock\ClockInterface;

final readonly class ListMyEventsHandler
{
    private const int HORIZON_DAYS = 180;

    public function __construct(private MemberIdentity $identity, private MemberEventReader $reader, private ClockInterface $clock)
    {
    }

    public function handle(ListMyEventsQuery $query): MemberEventPage
    {
        EventRules::page($query->limit, $query->offset);
        $person = $this->identity->currentPerson();
        if (null === $person) {
            return new MemberEventPage(false, [], false);
        }
        $now = $this->clock->now();
        $rows = $this->reader->list($person, $now, $now->modify('+'.self::HORIZON_DAYS.' days'), $query->mine, $query->limit + 1, $query->offset);

        return new MemberEventPage(true, \array_slice($rows, 0, $query->limit), \count($rows) > $query->limit);
    }
}

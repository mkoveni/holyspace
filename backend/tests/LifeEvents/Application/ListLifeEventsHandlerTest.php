<?php

declare(strict_types=1);

namespace App\Tests\LifeEvents\Application;

use App\LifeEvents\Application\Query\ListLifeEvents\ListLifeEventsHandler;
use App\LifeEvents\Application\Query\ListLifeEvents\ListLifeEventsQuery;
use App\LifeEvents\Domain\Details\GenericLifeEventDetails;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Enum\ParticipantRole;
use App\LifeEvents\Domain\Model\LifeEvent;
use App\LifeEvents\Domain\Model\LifeEventParticipant;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use App\LifeEvents\Domain\ValueObject\PersonId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ListLifeEventsHandlerTest extends TestCase
{
    public function testCalendarAndAnnualFilters(): void
    {
        $events = [];
        foreach (['2000-10-10', '2026-10-11', '2027-10-12', '2000-11-10'] as $date) {
            $events[] = LifeEvent::create(LifeEventId::generate(), LifeEventType::BIRTH, [LifeEventParticipant::create(PersonId::fromString('018f3a7b-4e4a-7a22-9b0e-111111111111'), ParticipantRole::SUBJECT)], new DateTimeImmutable($date), null, new GenericLifeEventDetails([]));
        }
        $repository = $this->createMock(LifeEventRepository::class);
        $repository->expects(self::exactly(3))->method('findByType')->with(LifeEventType::BIRTH)->willReturn($events);
        $handler = new ListLifeEventsHandler(new \App\LifeEvents\Infrastructure\ReadModel\RepositoryLifeEventReadStore($repository));
        self::assertCount(1, $handler(new ListLifeEventsQuery('birth', 10, 2026)));
        $result = $handler(new ListLifeEventsQuery('birth', 10, 2026, true));
        self::assertSame(['2000-10-10', '2026-10-11'], array_column($result, 'eventDate'));
        $events[0]->cancel();
        self::assertCount(1, $handler(new ListLifeEventsQuery('birth', 10, 2026, true)));
    }

    public function testInvalidMonthIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ListLifeEventsQuery(month: 13);
    }
}

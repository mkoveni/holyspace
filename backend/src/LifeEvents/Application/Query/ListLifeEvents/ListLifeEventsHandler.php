<?php
declare(strict_types=1);
namespace App\LifeEvents\Application\Query\ListLifeEvents;
use App\LifeEvents\Application\DTO\LifeEventView;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
final readonly class ListLifeEventsHandler
{
    public function __construct(private LifeEventRepository $lifeEvents) {}
    /** @return list<LifeEventView> */
    public function __invoke(ListLifeEventsQuery $query): array { $events=$query->type===null ? $this->lifeEvents->findAll() : $this->lifeEvents->findByType(LifeEventType::from($query->type)); return array_map(static fn($e)=>LifeEventView::fromDomain($e),$events); }
}

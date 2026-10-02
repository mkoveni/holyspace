<?php
declare(strict_types=1);
namespace App\LifeEvents\Application\Query\GetPersonLifeEvents;
use App\LifeEvents\Application\DTO\LifeEventView;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
use App\LifeEvents\Domain\ValueObject\PersonId;
final readonly class GetPersonLifeEventsHandler{public function __construct(private LifeEventRepository $lifeEvents){}public function __invoke(GetPersonLifeEventsQuery $query):array{return array_map(static fn($e)=>LifeEventView::fromDomain($e),$this->lifeEvents->findForPerson(PersonId::fromString($query->personId)));}}

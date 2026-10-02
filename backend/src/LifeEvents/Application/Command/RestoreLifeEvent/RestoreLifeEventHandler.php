<?php
declare(strict_types=1);
namespace App\LifeEvents\Application\Command\RestoreLifeEvent;
use App\LifeEvents\Domain\Exception\LifeEventNotFound;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
#[AsMessageHandler]
final readonly class RestoreLifeEventHandler {
 public function __construct(private LifeEventRepository $lifeEvents){}
 public function __invoke(RestoreLifeEventCommand $command):void{$id=LifeEventId::fromString($command->lifeEventId);$event=$this->lifeEvents->findById($id);if(!$event)throw LifeEventNotFound::withId($command->lifeEventId);$event->restore(new \DateTimeImmutable());$this->lifeEvents->save($event);}
}

<?php
declare(strict_types=1);
namespace App\LifeEvents\Application\Command\CancelLifeEvent;
use App\LifeEvents\Domain\Exception\LifeEventNotFound;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
#[AsMessageHandler]
final readonly class CancelLifeEventHandler {
 public function __construct(private LifeEventRepository $lifeEvents){}
 public function __invoke(CancelLifeEventCommand $command):void{$id=LifeEventId::fromString($command->lifeEventId);$event=$this->lifeEvents->findById($id);if(!$event)throw LifeEventNotFound::withId($command->lifeEventId);$event->cancel(new \DateTimeImmutable());$this->lifeEvents->save($event);}
}

<?php
declare(strict_types=1);
namespace App\LifeEvents\Application\Command\DeleteLifeEvent;
use App\LifeEvents\Domain\Exception\LifeEventNotFound;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
#[AsMessageHandler]
final readonly class DeleteLifeEventHandler {
 public function __construct(private LifeEventRepository $lifeEvents){}
 public function __invoke(DeleteLifeEventCommand $command):void{$id=LifeEventId::fromString($command->lifeEventId);if(!$this->lifeEvents->findById($id))throw LifeEventNotFound::withId($command->lifeEventId);$this->lifeEvents->delete($id);}
}

<?php
declare(strict_types=1);
namespace App\LifeEvents\Application\DTO;
use App\LifeEvents\Domain\Model\LifeEvent;
final readonly class LifeEventView
{
    public function __construct(public string $id,public string $type,public string $eventDate,public array $participants,public array $details,public ?string $notes,public string $status,public string $createdAt,public string $updatedAt){}
    public static function fromDomain(LifeEvent $event):self{return new self($event->id()->toString(),$event->type()->value,$event->eventDate()->format('Y-m-d'),array_map(static fn($p)=>['personId'=>$p->personId()->toString(),'role'=>$p->role()->value],$event->participants()),$event->details()->toArray(),$event->notes(),$event->status()->value,$event->createdAt()->format(DATE_ATOM),$event->updatedAt()->format(DATE_ATOM));}
}

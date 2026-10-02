<?php
declare(strict_types=1);
namespace App\LifeEvents\Infrastructure\Persistence\DBAL;
use App\LifeEvents\Domain\Details\EngagementDetails;
use App\LifeEvents\Domain\Details\GenericLifeEventDetails;
use App\LifeEvents\Domain\Details\MarriageDetails;
use App\LifeEvents\Domain\Enum\LifeEventStatus;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Enum\ParticipantRole;
use App\LifeEvents\Domain\Model\LifeEvent;
use App\LifeEvents\Domain\Model\LifeEventParticipant;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use App\LifeEvents\Domain\ValueObject\PersonId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;
final class LifeEventMapper
{
    /** @param list<array{person_id:string,role:string}> $participants */
    public function toDomain(array $row,array $participants): LifeEvent
    {
        $type=LifeEventType::from((string)$row['type']);
        $mapped=array_map(static fn(array $p)=>LifeEventParticipant::create(PersonId::fromString($p['person_id']),ParticipantRole::from($p['role'])),$participants);
        return LifeEvent::reconstitute(LifeEventId::fromString((string)$row['id']),$type,$mapped,new DateTimeImmutable((string)$row['event_date']),$row['notes']!==null?(string)$row['notes']:null,LifeEventStatus::from((string)$row['status']),$this->details($type,(string)($row['details_json']??'{}')),new AuditTimestamps($this->date($row['created_at']),$this->date($row['updated_at'])));
    }
    /** @return array<string,mixed> */ public function toRow(LifeEvent $event): array { return ['id'=>$event->id()->toString(),'type'=>$event->type()->value,'event_date'=>$event->eventDate()->format('Y-m-d'),'notes'=>$event->notes(),'status'=>$event->status()->value,'details_json'=>json_encode($event->details()->toArray(),JSON_THROW_ON_ERROR),'created_at'=>$event->createdAt()->format('Y-m-d H:i:s'),'updated_at'=>$event->updatedAt()->format('Y-m-d H:i:s')]; }
    private function details(LifeEventType $type,string $json): object { $data=json_decode($json,true,512,JSON_THROW_ON_ERROR); return match($type){LifeEventType::MARRIAGE=>MarriageDetails::fromArray($data),LifeEventType::ENGAGEMENT=>EngagementDetails::fromArray($data),default=>new GenericLifeEventDetails(is_array($data)?$data:[])}; }
    private function date(mixed $value): DateTimeImmutable { return $value instanceof DateTimeImmutable?$value:new DateTimeImmutable((string)$value); }
}

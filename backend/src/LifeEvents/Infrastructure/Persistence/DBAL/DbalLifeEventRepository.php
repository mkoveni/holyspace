<?php
declare(strict_types=1);
namespace App\LifeEvents\Infrastructure\Persistence\DBAL;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Model\LifeEvent;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use App\LifeEvents\Domain\ValueObject\PersonId;
use Doctrine\DBAL\Connection;
use DateTimeImmutable;
final readonly class DbalLifeEventRepository implements LifeEventRepository
{
    public function __construct(private Connection $connection,private LifeEventMapper $mapper){}
    public function save(LifeEvent $event): void {
        $data=$this->mapper->toRow($event); $this->connection->transactional(function()use($event,$data):void{
            $exists=$this->connection->fetchOne('SELECT 1 FROM life_events WHERE id=:id',['id'=>$event->id()->toString()]);
            if($exists){$row=$data;unset($row['id']);$this->connection->update('life_events',$row,['id'=>$event->id()->toString()]);}
            else{$this->connection->insert('life_events',$data);}
            $this->connection->delete('life_event_participants',['life_event_id'=>$event->id()->toString()]);
            foreach($event->participants() as $participant){$this->connection->insert('life_event_participants',['life_event_id'=>$event->id()->toString(),'person_id'=>$participant->personId()->toString(),'role'=>$participant->role()->value]);}
        });
    }
    public function delete(LifeEventId $id):void{$this->connection->delete('life_events',['id'=>$id->toString()]);}
    public function findById(LifeEventId $id):?LifeEvent{return $this->hydrate($this->connection->fetchAssociative('SELECT * FROM life_events WHERE id=:id',['id'=>$id->toString()]));}
    public function findForPerson(PersonId $personId):array{$rows=$this->connection->fetchAllAssociative('SELECT e.* FROM life_events e INNER JOIN life_event_participants p ON p.life_event_id=e.id WHERE p.person_id=:person_id GROUP BY e.id ORDER BY e.event_date DESC,e.id DESC',['person_id'=>$personId->toString()]);return $this->hydrateMany($rows);}
    public function findByType(LifeEventType $type):array{return $this->hydrateMany($this->connection->fetchAllAssociative('SELECT * FROM life_events WHERE type=:type ORDER BY event_date DESC,id DESC',['type'=>$type->value]));}
    public function findUpcoming(DateTimeImmutable $from,DateTimeImmutable $to):array{return $this->hydrateMany($this->connection->fetchAllAssociative('SELECT * FROM life_events WHERE event_date>=:from_date AND event_date<=:to_date AND status<>:cancelled ORDER BY event_date ASC,id ASC',['from_date'=>$from->format('Y-m-d'),'to_date'=>$to->format('Y-m-d'),'cancelled'=>'cancelled']));}
    public function findAll():array{return $this->hydrateMany($this->connection->fetchAllAssociative('SELECT * FROM life_events ORDER BY event_date DESC,id DESC'));}
    public function existsForParticipantsTypeOnDate(array $personIds,LifeEventType $type,DateTimeImmutable $date,?LifeEventId $ignoreId=null):bool{
        if($personIds===[])return false; $ids=array_map(static fn(PersonId $p)=>$p->toString(),$personIds); $placeholders=implode(',',array_fill(0,count($ids),'?')); $params=array_merge([$type->value,$date->format('Y-m-d')],$ids); $sql='SELECT e.id FROM life_events e INNER JOIN life_event_participants p ON p.life_event_id=e.id WHERE e.type=? AND e.event_date=? AND p.person_id IN ('.$placeholders.')'; if($ignoreId){$sql.=' AND e.id<>?';$params[]=$ignoreId->toString();} $sql.=' GROUP BY e.id HAVING COUNT(DISTINCT p.person_id)=? AND (SELECT COUNT(*) FROM life_event_participants pp WHERE pp.life_event_id=e.id)=?'; $params[] = count($ids); $params[]=count($ids); return $this->connection->fetchOne($sql,$params)!==false;
    }
    private function hydrate(mixed $row):?LifeEvent{if($row===false||$row===null)return null;$participants=$this->connection->fetchAllAssociative('SELECT person_id,role FROM life_event_participants WHERE life_event_id=:id ORDER BY person_id',['id'=>$row['id']]);return $this->mapper->toDomain($row,$participants);}
    private function hydrateMany(array $rows):array{return array_values(array_filter(array_map(fn(array $r)=>$this->hydrate($r),$rows)));}
}

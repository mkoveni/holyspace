<?php
declare(strict_types=1);
namespace App\Tests\LifeEvents\Application;
use App\LifeEvents\Application\Command\RecordLifeEvent\RecordLifeEventCommand;
use App\LifeEvents\Application\Command\RecordLifeEvent\RecordLifeEventHandler;
use App\LifeEvents\Application\Port\PersonExistenceChecker;
use App\LifeEvents\Domain\Model\LifeEvent;
use App\LifeEvents\Domain\Repository\LifeEventRepository;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use App\LifeEvents\Domain\ValueObject\PersonId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
final class RecordLifeEventHandlerTest extends TestCase
{
    public function testRecordsMarriageAndChecksParticipantsExist():void{$repo=new class implements LifeEventRepository{public array $events=[];public function save(LifeEvent $e):void{$this->events[]=$e;}public function delete(LifeEventId $id):void{}public function findById(LifeEventId $id):?LifeEvent{return null;}public function findForPerson(PersonId $p):array{return [];}public function findByType(\App\LifeEvents\Domain\Enum\LifeEventType $t):array{return [];}public function findUpcoming(DateTimeImmutable $f,DateTimeImmutable $t):array{return [];}public function findAll():array{return [];}public function existsForParticipantsTypeOnDate(array $p,\App\LifeEvents\Domain\Enum\LifeEventType $t,DateTimeImmutable $d,?LifeEventId $i=null):bool{return false;}};$people=new class implements PersonExistenceChecker{public function exists(PersonId $p):bool{return true;}};$handler=new RecordLifeEventHandler($repo,$people);$id=$handler(new RecordLifeEventCommand('marriage',[['personId'=>'018f3a7b-4e4a-7a22-9b0e-111111111111','role'=>'spouse'],['personId'=>'018f3a7b-4e4a-7a22-9b0e-222222222222','role'=>'spouse']],'2020-01-01',['venue'=>'Church']));self::assertInstanceOf(LifeEventId::class,$id);self::assertCount(1,$repo->events);self::assertSame('marriage',$repo->events[0]->type()->value);}
}

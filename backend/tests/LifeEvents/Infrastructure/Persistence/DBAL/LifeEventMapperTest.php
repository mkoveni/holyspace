<?php
declare(strict_types=1);
namespace App\Tests\LifeEvents\Infrastructure\Persistence\DBAL;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Enum\ParticipantRole;
use App\LifeEvents\Infrastructure\Persistence\DBAL\LifeEventMapper;
use PHPUnit\Framework\TestCase;
final class LifeEventMapperTest extends TestCase
{
    public function testMapsMarriageRowAndParticipants():void{$mapper=new LifeEventMapper();$row=['id'=>'018f3a7b-4e4a-7a22-9b0e-333333333333','type'=>'marriage','event_date'=>'2020-05-10','notes'=>'Church wedding','status'=>'completed','details_json'=>'{"venue":"Main Church","officiantName":"Pastor A","certificateReference":"CERT-1"}','created_at'=>'2026-01-01 10:00:00','updated_at'=>'2026-01-02 10:00:00'];$e=$mapper->toDomain($row,[['person_id'=>'018f3a7b-4e4a-7a22-9b0e-111111111111','role'=>'spouse'],['person_id'=>'018f3a7b-4e4a-7a22-9b0e-222222222222','role'=>'spouse']]);self::assertSame(LifeEventType::MARRIAGE,$e->type());self::assertSame(ParticipantRole::SPOUSE,$e->participants()[0]->role());self::assertSame('Main Church',$e->details()->toArray()['venue']);self::assertSame('2020-05-10',$e->eventDate()->format('Y-m-d'));}
}

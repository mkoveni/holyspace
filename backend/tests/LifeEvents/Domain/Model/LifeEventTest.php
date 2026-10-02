<?php
declare(strict_types=1);
namespace App\Tests\LifeEvents\Domain\Model;
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
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
final class LifeEventTest extends TestCase
{
    private function person(string $id):PersonId{return PersonId::fromString($id);}
    public function testMarriageRequiresTwoSpouses():void{$a=$this->person('018f3a7b-4e4a-7a22-9b0e-111111111111');$b=$this->person('018f3a7b-4e4a-7a22-9b0e-222222222222');$e=LifeEvent::create(LifeEventId::generate(),LifeEventType::MARRIAGE,[LifeEventParticipant::create($a,ParticipantRole::SPOUSE),LifeEventParticipant::create($b,ParticipantRole::SPOUSE)],new DateTimeImmutable('2020-01-02'),null,new MarriageDetails(),new DateTimeImmutable('2026-01-01'));self::assertSame(LifeEventStatus::ACTIVE,$e->status());self::assertSame(6,$e->yearsMarriedOn(new DateTimeImmutable('2026-01-03')));self::assertSame('2027-01-02',$e->anniversaryIn(2027)->format('Y-m-d'));}
    public function testEngagementRequiresProposerAndRecipient():void{$a=$this->person('018f3a7b-4e4a-7a22-9b0e-111111111111');$b=$this->person('018f3a7b-4e4a-7a22-9b0e-222222222222');$e=LifeEvent::create(LifeEventId::generate(),LifeEventType::ENGAGEMENT,[LifeEventParticipant::create($a,ParticipantRole::PROPOSER),LifeEventParticipant::create($b,ParticipantRole::RECIPIENT)],new DateTimeImmutable('2026-01-02'),null,new EngagementDetails(new DateTimeImmutable('2027-01-01')),new DateTimeImmutable('2026-01-01'));self::assertSame(LifeEventType::ENGAGEMENT,$e->type());}
    public function testMutationTouchesUpdatedAt():void{$p=$this->person('018f3a7b-4e4a-7a22-9b0e-111111111111');$created=new DateTimeImmutable('2026-01-01 10:00:00');$updated=new DateTimeImmutable('2026-01-02 10:00:00');$e=LifeEvent::create(LifeEventId::generate(),LifeEventType::BIRTH,[LifeEventParticipant::create($p,ParticipantRole::SUBJECT)],new DateTimeImmutable('1990-01-01'),null,new GenericLifeEventDetails(),$created);$e->complete($updated);self::assertEquals($created,$e->createdAt());self::assertEquals($updated,$e->updatedAt());self::assertSame(LifeEventStatus::COMPLETED,$e->status());}
}

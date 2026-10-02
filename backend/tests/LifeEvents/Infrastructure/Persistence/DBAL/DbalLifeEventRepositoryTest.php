<?php
declare(strict_types=1);
namespace App\Tests\LifeEvents\Infrastructure\Persistence\DBAL;
use App\LifeEvents\Domain\Details\MarriageDetails;
use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Enum\ParticipantRole;
use App\LifeEvents\Domain\Model\LifeEvent;
use App\LifeEvents\Domain\Model\LifeEventParticipant;
use App\LifeEvents\Domain\ValueObject\LifeEventId;
use App\LifeEvents\Domain\ValueObject\PersonId;
use App\LifeEvents\Infrastructure\Persistence\DBAL\DbalLifeEventRepository;
use App\LifeEvents\Infrastructure\Persistence\DBAL\LifeEventMapper;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
final class DbalLifeEventRepositoryTest extends TestCase
{
    public function testRoundTrip():void{$db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);$db->executeStatement('CREATE TABLE life_events(id CHAR(36) PRIMARY KEY,type VARCHAR(50),event_date DATE,notes TEXT,status VARCHAR(30),details_json TEXT,created_at DATETIME,updated_at DATETIME)');$db->executeStatement('CREATE TABLE life_event_participants(life_event_id CHAR(36),person_id CHAR(36),role VARCHAR(30),PRIMARY KEY(life_event_id,person_id))');$repo=new DbalLifeEventRepository($db,new LifeEventMapper());$a=PersonId::fromString('018f3a7b-4e4a-7a22-9b0e-111111111111');$b=PersonId::fromString('018f3a7b-4e4a-7a22-9b0e-222222222222');$e=LifeEvent::create(LifeEventId::generate(),LifeEventType::MARRIAGE,[LifeEventParticipant::create($a,ParticipantRole::SPOUSE),LifeEventParticipant::create($b,ParticipantRole::SPOUSE)],new DateTimeImmutable('2020-01-01'),null,new MarriageDetails('Church'),new DateTimeImmutable('2026-01-01'));$repo->save($e);$loaded=$repo->findById($e->id());self::assertNotNull($loaded);self::assertSame($e->id()->toString(),$loaded->id()->toString());self::assertCount(2,$loaded->participants());self::assertSame('Church',$loaded->details()->toArray()['venue']);}
}

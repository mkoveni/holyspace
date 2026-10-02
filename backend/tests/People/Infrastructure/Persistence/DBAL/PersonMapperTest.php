<?php
declare(strict_types=1);
namespace App\Tests\People\Infrastructure\Persistence\DBAL;
use App\People\Domain\Enum\Gender; use App\People\Domain\Enum\MembershipStatus; use App\People\Infrastructure\Persistence\DBAL\PersonMapper; use PHPUnit\Framework\TestCase;
final class PersonMapperTest extends TestCase {public function testMapsDatabaseRowToDomainAndBack():void{$mapper=new PersonMapper();$person=$mapper->toDomain(['id'=>'018f3a7b-4e4a-7a22-9b0e-111111111111','first_name'=>'John','last_name'=>'Doe','date_of_birth'=>'1990-01-01','gender'=>'male','email'=>'john@example.com','phone'=>'+27123456789','status'=>'active','created_at'=>'2026-01-01 10:00:00','updated_at'=>'2026-01-02 10:00:00']);self::assertSame(Gender::MALE,$person->gender());self::assertSame(MembershipStatus::ACTIVE,$person->status());self::assertSame('john@example.com',$person->email()?->value());self::assertSame('male',$mapper->toRow($person)['gender']);}}

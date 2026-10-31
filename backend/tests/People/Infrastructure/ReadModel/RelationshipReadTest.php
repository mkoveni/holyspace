<?php

declare(strict_types=1);

namespace App\Tests\People\Infrastructure\ReadModel;

use App\People\Infrastructure\ReadModel\DBAL\DbalPeopleReadRepository;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class RelationshipReadTest extends TestCase
{
    public function testBothPeopleSeeTheSameRelationshipWithCorrectPerspective(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE people_persons (id TEXT, first_name TEXT, last_name TEXT)');
        $db->executeStatement('CREATE TABLE people_relationships (id TEXT, person_id TEXT, related_person_id TEXT, type TEXT, created_at TEXT, ended_at TEXT)');
        $db->insert('people_persons', ['id' => 'a', 'first_name' => 'Alice', 'last_name' => 'Smith']);
        $db->insert('people_persons', ['id' => 'b', 'first_name' => 'Bob', 'last_name' => 'Smith']);
        $read = new DbalPeopleReadRepository($db);
        foreach (['spouse' => 'spouse', 'parent' => 'child', 'child' => 'parent', 'sibling' => 'sibling', 'guardian' => 'ward', 'other' => 'other'] as $type => $inverse) {
            $db->executeStatement('DELETE FROM people_relationships');
            $db->insert('people_relationships', ['id' => 'r', 'person_id' => 'a', 'related_person_id' => 'b', 'type' => $type, 'created_at' => '2026-10-10', 'ended_at' => null]);
            $forward = $read->relationshipsForPerson('a')[0];
            $reverse = $read->relationshipsForPerson('b')[0];
            self::assertSame('Bob', $forward['related_first_name']);
            self::assertSame($type, $forward['type']);
            self::assertSame('Alice', $reverse['related_first_name']);
            self::assertSame($inverse, $reverse['type']);
            self::assertSame('b', $reverse['person_id']);
            self::assertSame('a', $reverse['related_person_id']);
            self::assertSame($forward['id'], $reverse['id']);
            $db->executeStatement("UPDATE people_relationships SET ended_at = '2026-10-11'");
            self::assertSame('2026-10-11', $read->relationshipsForPerson('b')[0]['ended_at']);
        }
    }
}

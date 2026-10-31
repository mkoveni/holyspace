<?php

declare(strict_types=1);

namespace App\Tests\LifeEvents\Application;

use App\LifeEvents\Application\Command\RecordLifeEvent\RecordLifeEventCommand;
use App\LifeEvents\Application\Command\RecordLifeEvent\RecordLifeEventHandler;
use App\LifeEvents\Application\Command\UpdateLifeEvent\UpdateLifeEventCommand;
use App\LifeEvents\Application\Command\UpdateLifeEvent\UpdateLifeEventHandler;
use App\LifeEvents\Application\Port\PersonExistenceChecker;
use App\LifeEvents\Infrastructure\Persistence\DBAL\DbalLifeEventRepository;
use App\LifeEvents\Infrastructure\Persistence\DBAL\LifeEventMapper;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class ParticipantPayloadTest extends TestCase
{
    public function testCreateAndUpdateWithIdOnlyAndRoleAwarePayloads(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE life_events(id TEXT PRIMARY KEY,type TEXT,event_date TEXT,notes TEXT,status TEXT,details_json TEXT,created_at TEXT,updated_at TEXT)');
        $db->executeStatement('CREATE TABLE life_event_participants(life_event_id TEXT,person_id TEXT,role TEXT,PRIMARY KEY(life_event_id,person_id))');
        $repository = new DbalLifeEventRepository($db, new LifeEventMapper());
        $people = $this->createStub(PersonExistenceChecker::class);
        $people->method('exists')->willReturn(true);
        $create = new RecordLifeEventHandler($repository, $people);
        $update = new UpdateLifeEventHandler($repository, $people);
        $ids = ['018f3a7b-4e4a-7a22-9b0e-111111111111', '018f3a7b-4e4a-7a22-9b0e-222222222222'];
        foreach (['birth', 'marriage', 'engagement'] as $type) {
            $participants = 'birth' === $type ? [$ids[0]] : $ids;
            $id = $create(new RecordLifeEventCommand($type, $participants, '2026-10-10'));
            $event = $repository->findById($id);
            self::assertNotNull($event);
            $roles = match ($type) {
                'birth' => ['subject'],
                'marriage' => ['spouse', 'spouse'],
                'engagement' => ['proposer', 'recipient'],
            };
            self::assertSame($roles, array_map(fn ($p) => $p->role()->value, $event->participants()));
            $objects = array_map(fn ($personId, $role) => ['personId' => $personId, 'role' => $role], $participants, $roles);
            $update(new UpdateLifeEventCommand($id->toString(), $type, $objects, '2026-10-11', [], 'Updated'));
            self::assertSame('Updated', $repository->findById($id)->notes());
            $update(new UpdateLifeEventCommand($id->toString(), $type, $participants, '2026-10-12'));
            self::assertSame('2026-10-12', $repository->findById($id)->eventDate()->format('Y-m-d'));
            $newId = $create(new RecordLifeEventCommand($type, $objects, '2026-10-13'));
            self::assertSame($roles, array_map(fn ($p) => $p->role()->value, $repository->findById($newId)->participants()));
        }
    }
}

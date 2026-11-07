<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Shared;

use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\ErrorKind;
use PHPUnit\Framework\TestCase;

final class ParticipantAccessTest extends TestCase
{
    private const string A = '11111111-1111-4111-8111-111111111111';
    private const string B = '22222222-2222-4222-8222-222222222222';
    private const string C = '33333333-3333-4333-8333-333333333333';

    private CallLog $log;

    protected function setUp(): void
    {
        $this->log = new CallLog();
    }

    /** @param array<string, bool> $people person id => available */
    private function access(array $people): ParticipantAccess
    {
        return new ParticipantAccess(new RecordingPeople($this->log, $people), new RecordingChildLocks($this->log));
    }

    public function testPeopleAreLockedInIdOrderThenTheChildGuard(): void
    {
        $this->access([self::A => true, self::B => true, self::C => true])->lockChild(self::C, self::A, self::C, self::B);

        self::assertSame(['person:'.self::A, 'person:'.self::B, 'person:'.self::C, 'guard:'.self::C], $this->log->entries);
    }

    public function testAMissingPersonCannotBeLocked(): void
    {
        try {
            $this->access([self::A => true])->lockChild(self::A, self::B);
            self::fail('Expected not found.');
        } catch (ChildrenException $error) {
            self::assertSame(ErrorKind::NotFound, $error->kind());
            self::assertSame('Person was not found.', $error->getMessage());
            self::assertNotContains('guard:'.self::A, $this->log->entries, 'the guard is only taken once every person exists');
        }
    }

    public function testUnavailablePeopleAreAConflictButStillFindable(): void
    {
        $access = $this->access([self::A => false]);

        self::assertFalse($access->person(self::A)->available);
        $this->expectExceptionMessage('Restore the person or review their life status first.');
        $access->available(self::A);
    }

    public function testUnknownPeopleAreNotFound(): void
    {
        $this->expectExceptionMessage('Person was not found.');

        $this->access([])->person(self::A);
    }
}

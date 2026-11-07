<?php

declare(strict_types=1);

namespace App\Tests\People\Domain;

use App\People\Domain\ParentagePolicy;
use App\People\Domain\PeopleException;
use App\People\Domain\PersonRelationship;
use App\People\Domain\ValueObject\RelationshipType;
use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RelationshipTest extends TestCase
{
    private const string A = '11111111-1111-4111-8111-111111111111';
    private const string B = '22222222-2222-4222-8222-222222222222';
    private const string C = '33333333-3333-4333-8333-333333333333';
    private const string D = '44444444-4444-4444-8444-444444444444';

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-06-01T10:00:00Z');
    }

    public function testSymmetricRelationshipsStoreTheSmallerIdFirst(): void
    {
        $spouse = $this->link(self::B, self::A, RelationshipType::SpouseOf);
        $parent = $this->link(self::B, self::A, RelationshipType::ParentOf);

        self::assertSame([self::A, self::B], [$spouse->sourceId, $spouse->targetId]);
        self::assertSame([self::B, self::A], [$parent->sourceId, $parent->targetId], 'directional types keep their direction');
    }

    public function testNobodyIsRelatedToThemselves(): void
    {
        $this->expectException(PeopleException::class);

        $this->link(self::A, self::A, RelationshipType::SiblingOf);
    }

    public function testEndingChecksTheVersionAndHappensOnce(): void
    {
        $link = $this->link(self::A, self::B, RelationshipType::GuardianOf);
        $ended = $link->end(0, $this->now->modify('+1 day'));

        self::assertSame(1, $ended->version);
        self::assertEquals($this->now->modify('+1 day'), $ended->validTo);
        $this->expectKind(ErrorKind::Conflict, fn (): PersonRelationship => $ended->end(1, $this->now->modify('+2 days')));
        $this->expectKind(ErrorKind::Conflict, fn (): PersonRelationship => $link->end(5, $this->now->modify('+1 day')));
    }

    public function testParentageCannotLoopBackOnItself(): void
    {
        $graph = [
            $this->link(self::A, self::B, RelationshipType::ParentOf),
            $this->link(self::B, self::C, RelationshipType::ParentOf),
        ];

        $this->expectKind(ErrorKind::Invalid, fn () => ParentagePolicy::requireAcyclic($this->link(self::C, self::A, RelationshipType::ParentOf), $graph, $this->now));
        $this->expectKind(ErrorKind::Invalid, fn () => ParentagePolicy::requireAcyclic($this->link(self::B, self::A, RelationshipType::ParentOf), $graph, $this->now));
        ParentagePolicy::requireAcyclic($this->link(self::A, self::C, RelationshipType::ParentOf), $graph, $this->now);
        ParentagePolicy::requireAcyclic($this->link(self::C, self::D, RelationshipType::ParentOf), $graph, $this->now);
        $this->addToAssertionCount(2);
    }

    public function testOnlyCurrentParentageAndParentageTypesMatter(): void
    {
        $ended = PersonRelationship::reconstitute(self::A, self::A, self::B, RelationshipType::ParentOf, $this->now->modify('-2 days'), $this->now->modify('-1 day'), 1);
        $guardian = $this->link(self::B, self::A, RelationshipType::GuardianOf);

        ParentagePolicy::requireAcyclic($this->link(self::B, self::A, RelationshipType::ParentOf), [$ended], $this->now);
        ParentagePolicy::requireAcyclic($this->link(self::B, self::A, RelationshipType::SpouseOf), [$this->link(self::A, self::B, RelationshipType::ParentOf)], $this->now);
        ParentagePolicy::requireAcyclic($this->link(self::A, self::B, RelationshipType::ParentOf), [$guardian], $this->now);
        $this->addToAssertionCount(3);
    }

    private function link(string $source, string $target, RelationshipType $type): PersonRelationship
    {
        return PersonRelationship::create(self::D, $source, $target, $type, $this->now);
    }

    private function expectKind(ErrorKind $kind, callable $action): void
    {
        try {
            $action();
        } catch (ContextException $error) {
            self::assertSame($kind, $error->kind());

            return;
        }
        self::fail('Expected a PeopleException.');
    }
}

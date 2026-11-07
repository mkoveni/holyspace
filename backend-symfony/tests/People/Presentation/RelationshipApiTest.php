<?php

declare(strict_types=1);

namespace App\Tests\People\Presentation;

final class RelationshipApiTest extends PeopleApiTestCase
{
    private function link(string $source, string $target, string $type): string
    {
        $this->call('POST', '/api/relationships', $this->admin, ['sourceId' => $source, 'targetId' => $target, 'type' => $type]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @return array<string|int, mixed> */
    private function relationshipsOf(string $person): array
    {
        $this->call('GET', "/api/people/$person/relationships", $this->reader);
        self::assertSame(200, $this->code());

        return $this->json();
    }

    public function testParentageIsShownFromEachPersonsPerspective(): void
    {
        $parent = $this->register('Parent');
        $child = $this->register('Child');
        $id = $this->link($parent, $child, 'PARENT_OF');

        $fromParent = $this->relationshipsOf($parent);
        self::assertSame([$id, $child, 'PARENT_OF'], [$fromParent[0]['id'], $fromParent[0]['relatedPersonId'], $fromParent[0]['type']]);
        $fromChild = $this->relationshipsOf($child);
        self::assertSame([$parent, 'CHILD_OF'], [$fromChild[0]['relatedPersonId'], $fromChild[0]['type']]);
        self::assertSame(1, $this->rows('people_audit', "action = 'relationship.created' AND target_reference = '$id'"));
    }

    public function testSymmetricRelationshipsCanBeCreatedFromEitherSideButOnlyOnce(): void
    {
        $a = $this->register('Ada');
        $b = $this->register('Bob');
        $this->link($a, $b, 'SPOUSE_OF');

        $this->call('POST', '/api/relationships', $this->admin, ['sourceId' => $b, 'targetId' => $a, 'type' => 'SPOUSE_OF']);
        self::assertSame(409, $this->code(), 'the mirrored pair overlaps the existing one');
        self::assertSame('SPOUSE_OF', $this->relationshipsOf($a)[0]['type']);
        self::assertSame('SPOUSE_OF', $this->relationshipsOf($b)[0]['type']);
        self::assertSame(1, $this->rows('people_relationships'));
    }

    public function testParentageCannotFormACycle(): void
    {
        $a = $this->register('Ada');
        $b = $this->register('Bob');
        $c = $this->register('Cy');
        $this->link($a, $b, 'PARENT_OF');
        $this->link($b, $c, 'PARENT_OF');

        $this->call('POST', '/api/relationships', $this->admin, ['sourceId' => $c, 'targetId' => $a, 'type' => 'PARENT_OF']);
        self::assertSame(400, $this->code());
        self::assertSame('Parentage cannot contain a cycle.', $this->message());
        $this->call('POST', '/api/relationships', $this->admin, ['sourceId' => $a, 'targetId' => $a, 'type' => 'SIBLING_OF']);
        self::assertSame(400, $this->code());
        self::assertSame(2, $this->rows('people_relationships'));
    }

    public function testEndingRecordsHistoryAndAllowsRecreating(): void
    {
        $a = $this->register('Ada');
        $b = $this->register('Bob');
        $id = $this->link($a, $b, 'GUARDIAN_OF');

        $this->call('POST', "/api/relationships/$id/end", $this->admin, ['expectedVersion' => 1]);
        self::assertSame(409, $this->code(), 'stale version');
        $this->call('POST', "/api/relationships/$id/end", $this->admin, ['expectedVersion' => 0]);
        self::assertSame(204, $this->code());
        self::assertNotNull($this->relationshipsOf($a)[0]['validTo']);
        self::assertSame(1, $this->relationshipsOf($a)[0]['version']);

        $this->call('POST', "/api/relationships/$id/end", $this->admin, ['expectedVersion' => 1]);
        self::assertSame(409, $this->code(), 'already ended');
        $this->link($a, $b, 'GUARDIAN_OF');
        self::assertCount(2, $this->relationshipsOf($a));
    }

    public function testArchivedOrUnknownPeopleCannotBeRelatedAndInputIsValidated(): void
    {
        $a = $this->register('Ada');
        $b = $this->register('Bob');
        $this->call('PUT', "/api/people/$b/archive", $this->admin, ['expectedVersion' => 0, 'archived' => true]);

        $this->call('POST', '/api/relationships', $this->admin, ['sourceId' => $a, 'targetId' => $b, 'type' => 'OTHER']);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/relationships', $this->admin, ['sourceId' => $a, 'targetId' => '00000000-0000-4000-8000-0000000000aa', 'type' => 'OTHER']);
        self::assertSame(404, $this->code());
        foreach ([['sourceId' => $a, 'type' => 'OTHER'], ['sourceId' => $a, 'targetId' => 'x', 'type' => 'OTHER'], ['sourceId' => $a, 'targetId' => $b, 'type' => 'COUSIN_OF']] as $body) {
            $this->call('POST', '/api/relationships', $this->admin, $body);
            self::assertSame(400, $this->code());
        }
        $this->call('POST', '/api/relationships/00000000-0000-4000-8000-0000000000aa/end', $this->admin, ['expectedVersion' => 0]);
        self::assertSame(404, $this->code());
        self::assertSame(0, $this->rows('people_relationships'));
    }
}

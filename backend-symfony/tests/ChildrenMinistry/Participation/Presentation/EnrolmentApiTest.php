<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Participation\Presentation;

final class EnrolmentApiTest extends ChildrenApiTestCase
{
    private string $programme;
    private string $juniors;
    private string $child;

    protected function setUp(): void
    {
        parent::setUp();
        $this->programme = $this->programme();
        $this->juniors = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $this->child = $this->person('Child', $this->yearsAgo(10));
    }

    /** @return array<string, mixed> */
    private function enrolBody(string $child, ?string $from = null, ?string $until = null): array
    {
        return ['programmeId' => $this->programme, 'childId' => $child, 'validFrom' => $from ?? $this->today(), 'validUntil' => $until ?? $this->today('+1 year')];
    }

    public function testEnrollingLocksTheChildAndIsAudited(): void
    {
        $id = $this->enrol($this->programme, $this->child);

        self::assertSame(1, $this->rows('children_child_guards', "child_id = '{$this->child}'"));
        self::assertSame(1, $this->rows('children_audit', "action = 'child.enrolled' AND target_id = '$id' AND actor_id = '{$this->adminId}'"));
        $this->call('GET', "/api/children/enrolments?childId={$this->child}", $this->reader);
        $enrolment = $this->json()[0];
        self::assertSame([$id, $this->programme, $this->child, $this->today('-1 day'), $this->today('+1 year'), null, 0], [$enrolment['id'], $enrolment['programmeId'], $enrolment['childId'], $enrolment['validFrom'], $enrolment['validUntil'], $enrolment['endedAt'], $enrolment['version']]);
    }

    public function testOnlyKnownChildrenUnderEighteenCanBeEnrolled(): void
    {
        $unknownAge = $this->person('Unknown', null);
        $adult = $this->person('Adult', $this->yearsAgo(30));
        $newborn = $this->person('Future', $this->today());

        foreach ([$unknownAge, $adult] as $person) {
            $this->call('POST', '/api/children/enrolments', $this->admin, $this->enrolBody($person));
            self::assertSame(400, $this->code(), $person);
        }
        $this->call('POST', '/api/children/enrolments', $this->admin, $this->enrolBody($newborn));
        self::assertSame(201, $this->code(), 'born today is still a child');
    }

    public function testEighteenthBirthdayEndsEligibilityOnTheDay(): void
    {
        $turnsEighteenToday = $this->person('Today', $this->yearsAgo(18));
        $turnsEighteenTomorrow = $this->person('Tomorrow', $this->yearsAgo(18, 1));

        $this->call('POST', '/api/children/enrolments', $this->admin, $this->enrolBody($turnsEighteenToday));
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/children/enrolments', $this->admin, $this->enrolBody($turnsEighteenTomorrow));
        self::assertSame(201, $this->code());
    }

    public function testEnrolmentInputAndReferencesAreChecked(): void
    {
        $valid = $this->enrolBody($this->child);
        $bad = [
            'end before start' => ['validUntil' => $this->today('-1 day')] + $valid,
            'zero length' => ['validUntil' => $valid['validFrom']] + $valid,
            'malformed date' => ['validFrom' => '01/02/2026'] + $valid,
            'missing date' => array_diff_key($valid, ['validUntil' => 1]),
            'missing child' => array_diff_key($valid, ['childId' => 1]),
            'bad programme id' => ['programmeId' => 'nope'] + $valid,
        ];
        foreach ($bad as $name => $body) {
            $this->call('POST', '/api/children/enrolments', $this->admin, $body);
            self::assertSame(400, $this->code(), $name);
        }
        $this->call('POST', '/api/children/enrolments', $this->admin, ['programmeId' => '00000000-0000-4000-8000-0000000000aa'] + $valid);
        self::assertSame(404, $this->code());
        self::assertSame('Programme was not found.', $this->message());
        $this->call('POST', '/api/children/enrolments', $this->admin, ['childId' => '00000000-0000-4000-8000-0000000000aa'] + $valid);
        self::assertSame(404, $this->code());
        self::assertSame('Person was not found.', $this->message());
        self::assertSame(0, $this->rows('children_enrolments'));
        self::assertSame(0, $this->rows('children_child_guards'), 'failed commands leave no trace');
    }

    public function testOverlappingEnrolmentsInOneProgrammeAreRefusedButOtherProgrammesAreFine(): void
    {
        $this->enrol($this->programme, $this->child);

        $this->call('POST', '/api/children/enrolments', $this->admin, $this->enrolBody($this->child, $this->today('+1 month'), $this->today('+2 months')));
        self::assertSame(409, $this->code());
        $other = $this->programme('Midweek');
        $this->enrol($other, $this->child);
        $this->call('POST', '/api/children/enrolments', $this->admin, $this->enrolBody($this->child, $this->today('+1 year'), $this->today('+2 years')));
        self::assertSame(201, $this->code(), 'back to back is not an overlap');
        self::assertSame(3, $this->rows('children_enrolments'));
    }

    public function testArchivedOrDeceasedChildrenCannotBeEnrolled(): void
    {
        $this->call('PUT', "/api/people/{$this->child}/archive", $this->admin, ['expectedVersion' => 0, 'archived' => true]);

        $this->call('POST', '/api/children/enrolments', $this->admin, $this->enrolBody($this->child));

        self::assertSame(409, $this->code());
        self::assertSame('Restore the person or review their life status first.', $this->message());
    }

    public function testClassPlacementMustFitTheEnrolmentProgrammeAndAgeRange(): void
    {
        $enrolment = $this->enrol($this->programme, $this->child, $this->today('-1 day'), $this->today('+6 months'));
        $toddlers = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Toddlers', 'minAgeMonths' => 12, 'maxAgeMonths' => 36]);
        $elsewhere = $this->create('/classes', ['programmeId' => $this->programme(), 'name' => 'Elsewhere', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $placement = fn (string $class, string $from, string $until): array => ['enrolmentId' => $enrolment, 'classId' => $class, 'validFrom' => $from, 'validUntil' => $until];

        $this->call('POST', '/api/children/class-assignments', $this->admin, $placement($toddlers, $this->today(), $this->today('+1 month')));
        self::assertSame(400, $this->code(), 'age 10 is outside 12-36 months');
        $this->call('POST', '/api/children/class-assignments', $this->admin, $placement($elsewhere, $this->today(), $this->today('+1 month')));
        self::assertSame(400, $this->code(), 'class of another programme');
        $this->call('POST', '/api/children/class-assignments', $this->admin, $placement($this->juniors, $this->today('-2 days'), $this->today('+1 month')));
        self::assertSame(400, $this->code(), 'starts before the enrolment');
        $this->call('POST', '/api/children/class-assignments', $this->admin, $placement($this->juniors, $this->today(), $this->today('+1 year')));
        self::assertSame(400, $this->code(), 'runs past the enrolment');
        $this->call('POST', '/api/children/class-assignments', $this->admin, $placement($this->juniors, $this->today(), $this->today('+1 month')));
        self::assertSame(201, $this->code(), $this->message());
        $this->call('POST', '/api/children/class-assignments', $this->admin, $placement($this->juniors, $this->today('+1 week'), $this->today('+2 months')));
        self::assertSame(409, $this->code(), 'an enrolment has one placement at a time');
        self::assertSame(1, $this->rows('children_audit', "action = 'class.assigned'"));
    }

    public function testPlacementReferencesAreChecked(): void
    {
        $enrolment = $this->enrol($this->programme, $this->child);
        $body = ['enrolmentId' => $enrolment, 'classId' => $this->juniors, 'validFrom' => $this->today(), 'validUntil' => $this->today('+1 month')];

        $this->call('POST', '/api/children/class-assignments', $this->admin, ['classId' => '00000000-0000-4000-8000-0000000000aa'] + $body);
        self::assertSame(404, $this->code());
        self::assertSame('MinistryClass was not found.', $this->message());
        $this->call('POST', '/api/children/class-assignments', $this->admin, ['enrolmentId' => '00000000-0000-4000-8000-0000000000aa'] + $body);
        self::assertSame(404, $this->code());
        self::assertSame('Enrolment was not found.', $this->message());
        $this->call('POST', '/api/children/class-assignments', $this->admin, ['validUntil' => $this->today()] + $body);
        self::assertSame(400, $this->code());
    }

    public function testEndingAnEnrolmentChecksTheVersionAndEndsItsPlacements(): void
    {
        $enrolment = $this->enrol($this->programme, $this->child);
        $assignment = $this->create('/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $this->juniors, 'validFrom' => $this->today(), 'validUntil' => $this->today('+1 month')]);

        $this->call('POST', '/api/children/enrolments/end', $this->admin, ['enrolmentId' => $enrolment, 'expectedVersion' => 5]);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/children/enrolments/end', $this->admin, ['enrolmentId' => $enrolment, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code());
        $this->call('POST', '/api/children/enrolments/end', $this->admin, ['enrolmentId' => $enrolment, 'expectedVersion' => 0]);
        self::assertSame(409, $this->code(), 'ending twice');

        $this->call('GET', "/api/children/class-assignments?childId={$this->child}", $this->reader);
        self::assertSame($assignment, $this->json()[0]['id']);
        self::assertNotNull($this->json()[0]['endedAt']);
        self::assertSame(1, $this->json()[0]['version'], 'ending the enrolment advanced the placement version');
        self::assertSame(1, $this->rows('children_audit', "action = 'enrolment.ended' AND target_id = '$enrolment'"));
    }

    public function testEndedEnrolmentsAllowACleanReEnrolment(): void
    {
        $enrolment = $this->enrol($this->programme, $this->child);
        $this->call('POST', '/api/children/enrolments/end', $this->admin, ['enrolmentId' => $enrolment, 'expectedVersion' => 0]);

        $fresh = $this->enrol($this->programme, $this->child);
        $this->create('/class-assignments', ['enrolmentId' => $fresh, 'classId' => $this->juniors, 'validFrom' => $this->today(), 'validUntil' => $this->today('+1 month')]);

        $this->call('GET', "/api/children/enrolments?childId={$this->child}", $this->reader);
        self::assertSame([$fresh, $enrolment], array_column($this->json(), 'id'), 'newest first');
        $this->call('POST', '/api/children/class-assignments', $this->admin, ['enrolmentId' => $enrolment, 'classId' => $this->juniors, 'validFrom' => $this->today('+2 months'), 'validUntil' => $this->today('+3 months')]);
        self::assertSame(400, $this->code(), 'an ended enrolment accepts no placements');
    }

    public function testEndingAPlacementKeepsTheEnrolment(): void
    {
        $enrolment = $this->enrol($this->programme, $this->child);
        $assignment = $this->create('/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $this->juniors, 'validFrom' => $this->today(), 'validUntil' => $this->today('+1 month')]);

        $this->call('POST', '/api/children/class-assignments/end', $this->admin, ['assignmentId' => $assignment, 'expectedVersion' => 1]);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/children/class-assignments/end', $this->admin, ['assignmentId' => $assignment, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code());

        $this->call('GET', "/api/children/enrolments?childId={$this->child}", $this->reader);
        self::assertNull($this->json()[0]['endedAt']);
        $this->call('POST', '/api/children/class-assignments/end', $this->admin, ['assignmentId' => '00000000-0000-4000-8000-0000000000aa', 'expectedVersion' => 0]);
        self::assertSame(404, $this->code());
        $this->call('POST', '/api/children/class-assignments/end', $this->admin, ['assignmentId' => $assignment]);
        self::assertSame(400, $this->code(), 'expectedVersion is required');
        self::assertSame(1, $this->rows('children_audit', "action = 'classassignment.ended'"));
    }

    public function testListingIsPagedAndNeedsAChild(): void
    {
        $this->enrol($this->programme, $this->child, $this->yearsAgo(2), $this->today('-1 year'));
        $this->enrol($this->programme, $this->child);

        $this->call('GET', "/api/children/enrolments?childId={$this->child}&limit=1&offset=1", $this->reader);
        self::assertCount(1, $this->json());
        foreach (['', '?childId=nope', "?childId={$this->child}&limit=0", "?childId={$this->child}&limit=101", "?childId={$this->child}&offset=-1"] as $query) {
            $this->call('GET', '/api/children/enrolments'.$query, $this->reader);
            self::assertSame(400, $this->code(), $query);
            $this->call('GET', '/api/children/class-assignments'.$query, $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
    }

    public function testReadersCannotWriteAndOutsidersCannotRead(): void
    {
        $enrolment = $this->enrol($this->programme, $this->child);
        $this->accountWith('nobody', ['people.read']);
        $writes = [
            ['/enrolments', $this->enrolBody($this->child)],
            ['/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $this->juniors, 'validFrom' => $this->today(), 'validUntil' => $this->today('+1 month')]],
            ['/enrolments/end', ['enrolmentId' => $enrolment, 'expectedVersion' => 0]],
            ['/class-assignments/end', ['assignmentId' => $enrolment, 'expectedVersion' => 0]],
        ];
        foreach ($writes as [$path, $body]) {
            $this->call('POST', '/api/children'.$path, $this->reader, $body);
            self::assertSame(403, $this->code(), "reader $path");
            $this->call('POST', '/api/children'.$path, null, $body);
            self::assertSame(401, $this->code(), "anonymous $path");
        }
        foreach (["/enrolments?childId={$this->child}", "/class-assignments?childId={$this->child}", "/programmes/{$this->programme}/children"] as $path) {
            $this->call('GET', '/api/children'.$path, $this->login('nobody'));
            self::assertSame(403, $this->code(), "outsider $path");
            $this->call('GET', '/api/children'.$path);
            self::assertSame(401, $this->code(), "anonymous $path");
        }
        self::assertSame(1, $this->rows('children_enrolments'));
        self::assertNull($this->connection->fetchOne('SELECT ended_at FROM children_enrolments LIMIT 1'));
    }
}

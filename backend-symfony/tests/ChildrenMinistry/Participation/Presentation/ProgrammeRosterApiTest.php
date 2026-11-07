<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Participation\Presentation;

final class ProgrammeRosterApiTest extends ChildrenApiTestCase
{
    private string $programme;
    private string $child;
    private string $enrolment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->programme = $this->programme();
        $juniors = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $this->child = $this->person('Child', $this->yearsAgo(10));
        $this->enrolment = $this->enrol($this->programme, $this->child);
        $this->create('/class-assignments', ['enrolmentId' => $this->enrolment, 'classId' => $juniors, 'validFrom' => $this->today(), 'validUntil' => $this->today('+1 year')]);
    }

    /** @return array<string|int, mixed> */
    private function roster(string $query = ''): array
    {
        $this->call('GET', "/api/children/programmes/{$this->programme}/children".$query, $this->reader);
        self::assertSame(200, $this->code(), $this->message());

        return $this->json();
    }

    public function testRosterShowsNamesStatusAndCurrentClassesWithoutSensitiveDetail(): void
    {
        $roster = $this->roster();

        self::assertSame(1, $roster['total']);
        self::assertSame(
            [
                'enrolmentId' => $this->enrolment,
                'childId' => $this->child,
                'name' => 'Child Mabasa',
                'validFrom' => $this->today('-1 day'),
                'validUntil' => $this->today('+1 year'),
                'status' => 'ACTIVE',
                'classes' => [['id' => $roster['children'][0]['classes'][0]['id'], 'name' => 'Juniors']],
                'readiness' => ['ready' => false, 'completed' => 3, 'total' => 7],
            ],
            $roster['children'][0],
        );
        $body = (string) $this->client->getResponse()->getContent();
        foreach (['evidence', 'allergies', 'guardianId', 'pickupCode', 'dateOfBirth'] as $secret) {
            self::assertStringNotContainsString($secret, $body);
        }
    }

    public function testSearchRunsBeforePagination(): void
    {
        $this->enrol($this->programme, $this->person('Zanele', $this->yearsAgo(8)));

        $found = $this->roster('?search=child&limit=1');
        self::assertSame(1, $found['total']);
        self::assertCount(1, $found['children']);
        self::assertStringNotContainsString('Zanele', (string) $this->client->getResponse()->getContent());

        self::assertCount(1, $this->roster('?limit=1&offset=1')['children']);
        self::assertSame([], $this->roster('?search=missing')['children']);
        self::assertSame(0, $this->roster('?search=missing')['total']);
        self::assertSame(2, $this->roster('?search=mabasa')['total'], 'matches the full name');
    }

    public function testStatusesRespectExclusiveEndDatesAndProgrammeScope(): void
    {
        $future = $this->person('Future', $this->yearsAgo(8));
        $this->enrol($this->programme, $future, $this->today('+1 day'), $this->today('+2 months'));
        $expired = $this->person('Expired', $this->yearsAgo(8));
        $this->enrol($this->programme, $expired, $this->today('-1 month'), $this->today());
        $this->enrol($this->programme('Other ministry'), $this->child, $this->today(), $this->today('+1 month'));

        $ids = static fn (array $page): array => array_column($page['children'], 'childId');
        self::assertSame([$this->child], $ids($this->roster('?status=ACTIVE')));
        self::assertSame([$future], $ids($this->roster('?status=UPCOMING')));
        self::assertSame([$expired], $ids($this->roster('?status=EXPIRED')), 'an enrolment ending today has expired');
        self::assertSame(3, $this->roster('?status=ALL')['total'], 'other programmes are not listed');
        self::assertSame('UPCOMING', $this->roster('?status=UPCOMING')['children'][0]['status']);

        $this->call('POST', '/api/children/enrolments/end', $this->admin, ['enrolmentId' => $this->enrolment, 'expectedVersion' => 0]);
        $ended = $this->roster('?status=ENDED');
        self::assertSame([$this->child], $ids($ended));
        self::assertSame('ENDED', $ended['children'][0]['status']);
        self::assertSame([], $ended['children'][0]['classes'], 'ended placements are not shown');
    }

    public function testFiltersAndProgrammeAreValidated(): void
    {
        foreach (['?status=UNKNOWN', '?limit=101', '?limit=0', '?offset=-1', '?limit=abc', '?search='.str_repeat('x', 121)] as $query) {
            $this->call('GET', "/api/children/programmes/{$this->programme}/children".$query, $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('GET', '/api/children/programmes/00000000-0000-4000-8000-0000000000aa/children', $this->reader);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/children/programmes/nope/children', $this->reader);
        self::assertSame(400, $this->code());
    }
}

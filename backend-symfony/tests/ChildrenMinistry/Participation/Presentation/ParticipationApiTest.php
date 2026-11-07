<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Participation\Presentation;

final class ParticipationApiTest extends ChildrenApiTestCase
{
    public function testProgrammesAreCreatedReadAndAudited(): void
    {
        $id = $this->create('/programmes', ['name' => ' Sunday children ', 'minStaff' => 1, 'childrenPerStaff' => 2]);

        $this->call('GET', "/api/children/programmes/$id", $this->reader);
        self::assertSame(200, $this->code());
        $programme = $this->json();
        self::assertSame(['id' => $id, 'name' => 'Sunday children', 'currentPolicyId' => null, 'minStaff' => 1, 'childrenPerStaff' => 2, 'version' => 0], array_diff_key($programme, ['createdAt' => 1]));
        self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T[\d:.]+Z\z/', $programme['createdAt']);
        self::assertSame(1, $this->rows('children_audit', "action = 'programme.created' AND target_id = '$id' AND actor_id = '{$this->adminId}' AND reason = ''"));
    }

    public function testProgrammeInputIsValidated(): void
    {
        $bad = [
            'blank name' => ['name' => '  ', 'minStaff' => 1, 'childrenPerStaff' => 2],
            'long name' => ['name' => str_repeat('x', 121), 'minStaff' => 1, 'childrenPerStaff' => 2],
            'no staff' => ['name' => 'A', 'minStaff' => 0, 'childrenPerStaff' => 2],
            'no ratio' => ['name' => 'A', 'minStaff' => 1, 'childrenPerStaff' => 0],
            'too many' => ['name' => 'A', 'minStaff' => 1, 'childrenPerStaff' => 101],
            'wrong type' => ['name' => 'A', 'minStaff' => 'many', 'childrenPerStaff' => 2],
            'empty body' => [],
        ];
        foreach ($bad as $name => $body) {
            $this->call('POST', '/api/children/programmes', $this->admin, $body);
            self::assertSame(400, $this->code(), $name);
        }
        self::assertSame(0, $this->rows('children_programmes'));
        self::assertSame(0, $this->rows('children_audit'));
    }

    public function testProgrammesAreSearchedOrderedAndPaged(): void
    {
        foreach (['Teens', 'Babies', 'Juniors', '100%_Kids'] as $name) {
            $this->create('/programmes', ['name' => $name, 'minStaff' => 1, 'childrenPerStaff' => 5]);
        }

        $this->call('GET', '/api/children/programmes', $this->reader);
        self::assertSame(['100%_Kids', 'Babies', 'Juniors', 'Teens'], array_column($this->json(), 'name'));
        $this->call('GET', '/api/children/programmes?search=JUN', $this->reader);
        self::assertSame(['Juniors'], array_column($this->json(), 'name'));
        $this->call('GET', '/api/children/programmes?search=%25', $this->reader);
        self::assertSame(['100%_Kids'], array_column($this->json(), 'name'), 'wildcards are literal');
        $this->call('GET', '/api/children/programmes?limit=2&offset=1', $this->reader);
        self::assertSame(['Babies', 'Juniors'], array_column($this->json(), 'name'));

        foreach (['limit=0', 'limit=101', 'offset=-1', 'limit=abc', 'search='.str_repeat('x', 121)] as $query) {
            $this->call('GET', "/api/children/programmes?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
    }

    public function testClassesBelongToAProgrammeAndKeepTheirAgeRange(): void
    {
        $programme = $this->create('/programmes', ['name' => 'Sunday', 'minStaff' => 1, 'childrenPerStaff' => 2]);
        $juniors = $this->create('/classes', ['programmeId' => $programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $babies = $this->create('/classes', ['programmeId' => $programme, 'name' => 'Babies', 'minAgeMonths' => 0, 'maxAgeMonths' => 24]);
        $this->create('/programmes', ['name' => 'Other', 'minStaff' => 1, 'childrenPerStaff' => 2]);

        $this->call('GET', "/api/children/classes?programmeId=$programme", $this->reader);
        self::assertSame(
            [
                ['id' => $babies, 'programmeId' => $programme, 'name' => 'Babies', 'minAgeMonths' => 0, 'maxAgeMonths' => 24],
                ['id' => $juniors, 'programmeId' => $programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156],
            ],
            $this->json(),
        );
        self::assertSame(2, $this->rows('children_audit', "action = 'class.created'"));
    }

    public function testClassInputIsValidated(): void
    {
        $programme = $this->create('/programmes', ['name' => 'Sunday', 'minStaff' => 1, 'childrenPerStaff' => 2]);
        $valid = ['programmeId' => $programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156];
        $bad = [
            'empty range' => ['maxAgeMonths' => 60] + $valid,
            'inverted' => ['minAgeMonths' => 200] + $valid,
            'beyond 18 years' => ['maxAgeMonths' => 217] + $valid,
            'negative' => ['minAgeMonths' => -1] + $valid,
            'blank name' => ['name' => ''] + $valid,
            'missing programme' => array_diff_key($valid, ['programmeId' => 1]),
            'bad programme id' => ['programmeId' => 'nope'] + $valid,
        ];
        foreach ($bad as $name => $body) {
            $this->call('POST', '/api/children/classes', $this->admin, $body);
            self::assertSame(400, $this->code(), $name);
        }
        $this->call('POST', '/api/children/classes', $this->admin, ['programmeId' => '00000000-0000-4000-8000-0000000000aa'] + $valid);
        self::assertSame(404, $this->code());
        self::assertSame('Programme was not found.', $this->message());
        self::assertSame(0, $this->rows('children_classes'));
    }

    public function testListingClassesNeedsAProgrammeId(): void
    {
        $this->call('GET', '/api/children/classes', $this->reader);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/children/classes?programmeId=nope', $this->reader);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/children/programmes/00000000-0000-4000-8000-0000000000aa', $this->reader);
        self::assertSame(404, $this->code());
    }

    public function testLocationsAndRoomsAreManagedAndListed(): void
    {
        $main = $this->create('/locations', ['name' => 'Main campus', 'timeZone' => 'Africa/Johannesburg']);
        $annex = $this->create('/locations', ['name' => 'Annex', 'timeZone' => 'UTC']);
        $roomB = $this->create('/rooms', ['locationId' => $main, 'name' => 'Room B', 'capacity' => 12]);
        $roomA = $this->create('/rooms', ['locationId' => $main, 'name' => 'Room A', 'capacity' => 10]);
        $this->create('/rooms', ['locationId' => $annex, 'name' => 'Hall', 'capacity' => 40]);

        $this->call('GET', '/api/children/locations', $this->reader);
        self::assertSame([['id' => $annex, 'name' => 'Annex', 'timeZone' => 'UTC'], ['id' => $main, 'name' => 'Main campus', 'timeZone' => 'Africa/Johannesburg']], $this->json());
        $this->call('GET', '/api/children/locations?limit=1&offset=1', $this->reader);
        self::assertSame(['Main campus'], array_column($this->json(), 'name'));

        $this->call('GET', "/api/children/rooms?locationId=$main", $this->reader);
        self::assertSame(
            [['id' => $roomA, 'locationId' => $main, 'name' => 'Room A', 'capacity' => 10], ['id' => $roomB, 'locationId' => $main, 'name' => 'Room B', 'capacity' => 12]],
            $this->json(),
        );
        $this->call('GET', '/api/children/rooms', $this->reader);
        self::assertSame([], $this->json(), 'rooms are listed per location');
        self::assertSame(2, $this->rows('children_audit', "action = 'location.created'"));
        self::assertSame(3, $this->rows('children_audit', "action = 'room.created'"));
    }

    public function testLocationAndRoomInputIsValidated(): void
    {
        $location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'UTC']);
        $locations = [
            'unknown zone' => ['name' => 'X', 'timeZone' => 'Mars/Olympus'],
            'blank zone' => ['name' => 'X', 'timeZone' => ''],
            'blank name' => ['name' => ' ', 'timeZone' => 'UTC'],
        ];
        foreach ($locations as $name => $body) {
            $this->call('POST', '/api/children/locations', $this->admin, $body);
            self::assertSame(400, $this->code(), $name);
        }
        $rooms = [
            'no capacity' => ['locationId' => $location, 'name' => 'A', 'capacity' => 0],
            'negative capacity' => ['locationId' => $location, 'name' => 'A', 'capacity' => -5],
            'blank name' => ['locationId' => $location, 'name' => '', 'capacity' => 5],
            'missing location' => ['name' => 'A', 'capacity' => 5],
        ];
        foreach ($rooms as $name => $body) {
            $this->call('POST', '/api/children/rooms', $this->admin, $body);
            self::assertSame(400, $this->code(), $name);
        }
        $this->call('POST', '/api/children/rooms', $this->admin, ['locationId' => '00000000-0000-4000-8000-0000000000aa', 'name' => 'A', 'capacity' => 5]);
        self::assertSame(404, $this->code());
        self::assertSame('Location was not found.', $this->message());
        self::assertSame(1, $this->rows('children_locations'));
        self::assertSame(0, $this->rows('children_rooms'));
    }

    public function testReadersCannotWriteAndOutsidersCannotRead(): void
    {
        $programme = $this->create('/programmes', ['name' => 'Sunday', 'minStaff' => 1, 'childrenPerStaff' => 2]);
        $this->accountWith('nobody', ['people.read']);
        $writes = [
            ['/programmes', ['name' => 'X', 'minStaff' => 1, 'childrenPerStaff' => 1]],
            ['/classes', ['programmeId' => $programme, 'name' => 'X', 'minAgeMonths' => 0, 'maxAgeMonths' => 12]],
            ['/locations', ['name' => 'X', 'timeZone' => 'UTC']],
            ['/rooms', ['locationId' => $programme, 'name' => 'X', 'capacity' => 1]],
        ];
        foreach ($writes as [$path, $body]) {
            $this->call('POST', '/api/children'.$path, $this->reader, $body);
            self::assertSame(403, $this->code(), "reader $path");
            $this->call('POST', '/api/children'.$path, null, $body);
            self::assertSame(401, $this->code(), "anonymous $path");
        }
        foreach (['/programmes', "/programmes/$programme", "/classes?programmeId=$programme", '/locations', '/rooms'] as $path) {
            $this->call('GET', '/api/children'.$path, $this->login('nobody'));
            self::assertSame(403, $this->code(), "outsider $path");
            $this->call('GET', '/api/children'.$path);
            self::assertSame(401, $this->code(), "anonymous $path");
        }
        self::assertSame(1, $this->rows('children_programmes'));
    }

    public function testChildrenManageImpliesReadAccess(): void
    {
        $this->accountWith('coordinator', ['children.manage']);
        $token = $this->login('coordinator');

        $this->call('GET', '/api/children/programmes', $token);

        self::assertSame(200, $this->code());
    }
}

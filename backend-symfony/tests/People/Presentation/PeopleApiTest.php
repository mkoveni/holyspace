<?php

declare(strict_types=1);

namespace App\Tests\People\Presentation;

use App\People\Infrastructure\Persistence\DbalPersonStore;
use App\People\Infrastructure\PublishedPersonDirectory;
use App\People\Infrastructure\PublishedPersonEmailDirectory;

final class PeopleApiTest extends PeopleApiTestCase
{
    public function testMetadataListsFourMembershipTypesAndTheTitleCatalogue(): void
    {
        $this->call('GET', '/api/people/metadata', $this->reader);

        self::assertSame(200, $this->code());
        $metadata = $this->json();
        self::assertSame(
            ['ACTIVE_MEMBER' => 'Active Member', 'REGULAR_VISITOR' => 'Regular Visitor', 'VISITOR' => 'Visitor', 'TRANSFERRED' => 'Transferred'],
            array_column($metadata['membershipTypes'], 'label', 'code'),
        );
        self::assertContains('Dr', array_column($metadata['titles'], 'label'));
        self::assertSame(['MALE', 'FEMALE', 'UNKNOWN'], $metadata['genders']);
        self::assertSame(['PARENT_OF', 'GUARDIAN_OF', 'SPOUSE_OF', 'SIBLING_OF', 'OTHER'], $metadata['relationshipTypes']);
        self::assertSame(18, $metadata['adultAge']);
        self::assertSame('Africa/Johannesburg', $metadata['timeZone']);
    }

    public function testTitlesCanBeAddedAndUsed(): void
    {
        $this->call('POST', '/api/people/titles', $this->admin, ['code' => 'pastor', 'label' => 'Pastor']);
        self::assertSame(204, $this->code());
        $id = $this->register('Solly', ['titleCode' => 'pastor']);

        self::assertSame('PASTOR', $this->person($id)['details']['titleCode']);
        self::assertSame(1, $this->rows('people_audit', "action = 'title.created' AND target_reference = 'PASTOR'"));

        $this->call('POST', '/api/people/titles', $this->admin, ['code' => 'PASTOR', 'label' => 'Again']);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/people/titles', $this->admin, ['code' => '9bad', 'label' => 'Bad']);
        self::assertSame(400, $this->code());
    }

    public function testRegisteringRecordsAnAuditEntryAndDefaultsToVisitor(): void
    {
        $id = $this->register('Solly');

        $person = $this->person($id);
        self::assertSame('VISITOR', $person['membershipType']);
        self::assertSame('UNKNOWN', $person['details']['gender']);
        self::assertSame('UNKNOWN', $person['details']['lifeStatus']);
        self::assertSame('UNKNOWN', $person['ageCategory']);
        self::assertNull($person['archivedAt']);
        self::assertSame(0, $person['version']);
        self::assertSame([], $person['contacts']);
        self::assertSame(1, $this->rows('people_audit', "action = 'person.registered' AND target_reference = '$id' AND actor_id = '{$this->adminId}'"));
    }

    public function testRegistrationValidatesInput(): void
    {
        $invalid = [
            'unknown membership' => ['person' => ['firstName' => 'A', 'lastName' => 'B'], 'membershipType' => 'PROSPECT'],
            'unknown title' => ['person' => ['firstName' => 'A', 'lastName' => 'B', 'titleCode' => 'MISSING']],
            'missing person' => ['membershipType' => 'VISITOR'],
            'blank name' => ['person' => ['firstName' => ' ', 'lastName' => 'B']],
            'future birth' => ['person' => ['firstName' => 'A', 'lastName' => 'B', 'dateOfBirth' => '2999-01-01']],
            'malformed date' => ['person' => ['firstName' => 'A', 'lastName' => 'B', 'dateOfBirth' => '01/02/2000']],
            'impossible date' => ['person' => ['firstName' => 'A', 'lastName' => 'B', 'dateOfBirth' => '2000-02-30']],
            'unknown gender' => ['person' => ['firstName' => 'A', 'lastName' => 'B', 'gender' => 'ROBOT']],
            'death without deceased status' => ['person' => ['firstName' => 'A', 'lastName' => 'B', 'dateOfDeath' => '2020-01-01']],
        ];
        foreach ($invalid as $name => $body) {
            $this->call('POST', '/api/people', $this->admin, $body);
            self::assertSame(400, $this->code(), $name);
        }
        self::assertSame(0, $this->rows('people_persons'));
    }

    public function testAgeIsDerivedOnTheRequestedDate(): void
    {
        $id = $this->register('Child', ['dateOfBirth' => '2008-10-26']);

        foreach ([['2026-10-25', 'CHILD'], ['2026-10-26', 'ADULT'], ['2008-10-25', 'UNKNOWN']] as [$asOf, $category]) {
            $this->call('GET', "/api/people/$id?asOf=$asOf", $this->admin);
            self::assertSame($category, $this->json()['ageCategory'], $asOf);
            self::assertSame($asOf, $this->json()['ageAsOf']);
        }
        $this->call('GET', "/api/people/$id?asOf=nonsense", $this->admin);
        self::assertSame(400, $this->code());
    }

    public function testUpdateChecksTheVersionAndKeepsHistory(): void
    {
        $id = $this->register('Solly');
        $profile = ['expectedVersion' => 0, 'person' => ['firstName' => 'Solly', 'lastName' => 'Mabasa', 'titleCode' => 'DR'], 'membershipType' => 'TRANSFERRED'];

        $this->call('PUT', "/api/people/$id", $this->admin, $profile);
        self::assertSame(204, $this->code());
        self::assertSame('TRANSFERRED', $this->person($id)['membershipType']);

        $this->call('PUT', "/api/people/$id", $this->admin, $profile);
        self::assertSame(409, $this->code());
        self::assertSame('The record has changed. Reload and try again.', $this->message());

        $this->call('PUT', "/api/people/$id", $this->admin, ['membershipType' => null] + $profile);
        self::assertSame(400, $this->code());
        self::assertSame(1, $this->version('people_persons', $id));
        self::assertSame(1, $this->rows('people_audit', "action = 'person.updated' AND target_reference = '$id'"));
    }

    public function testArchiveHidesFromSearchUntilRestored(): void
    {
        $id = $this->register('Solly');

        $this->call('PUT', "/api/people/$id/archive", $this->admin, ['expectedVersion' => 0, 'archived' => true]);
        self::assertSame(204, $this->code());
        self::assertNotNull($this->person($id)['archivedAt']);

        $this->call('GET', '/api/people?search=solly', $this->admin);
        self::assertSame([], $this->json());
        $this->call('GET', '/api/people?search=solly&includeArchived=true', $this->admin);
        self::assertCount(1, $this->json());

        $this->call('PUT', "/api/people/$id/archive", $this->admin, ['expectedVersion' => 1, 'archived' => false]);
        self::assertNull($this->person($id)['archivedAt']);
        self::assertSame(1, $this->rows('people_audit', "action = 'person.archived'"));
        self::assertSame(1, $this->rows('people_audit', "action = 'person.restored'"));
    }

    public function testContactsKeepHistoryWithOnePrimaryPerKind(): void
    {
        $a = $this->register('Solly');
        $b = $this->register('Child');
        $this->call('POST', "/api/people/$a/contacts", $this->admin, ['expectedVersion' => 0, 'kind' => 'EMAIL', 'value' => 'Family@Church.org', 'primary' => true]);
        self::assertSame(201, $this->code());
        $first = (string) $this->json()['id'];
        $this->call('POST', "/api/people/$b/contacts", $this->admin, ['expectedVersion' => 0, 'kind' => 'EMAIL', 'value' => 'family@church.org', 'primary' => true]);
        self::assertSame(201, $this->code(), 'people may share an address');

        $this->call('POST', "/api/people/$a/contacts", $this->admin, ['expectedVersion' => 1, 'kind' => 'EMAIL', 'value' => 'other@church.org', 'primary' => true]);
        self::assertSame(201, $this->code());
        self::assertSame(1, $this->rows('people_contact_methods', "person_id = '$a' AND is_primary AND ended_at IS NULL"));

        $this->call('POST', "/api/people/$a/contacts", $this->admin, ['expectedVersion' => 2, 'kind' => 'EMAIL', 'value' => 'family@church.org']);
        self::assertSame(409, $this->code(), 'duplicate of an active contact');
        self::assertSame(2, $this->version('people_persons', $a), 'the failed command leaves no trace');

        $this->call('POST', "/api/people/$a/contacts/$first/end", $this->admin, ['expectedVersion' => 2]);
        self::assertSame(204, $this->code());
        $this->call('POST', "/api/people/$a/contacts/$first/end", $this->admin, ['expectedVersion' => 3]);
        self::assertSame(409, $this->code(), 'already ended');

        $contacts = $this->person($a)['contacts'];
        self::assertSame(['family@church.org', 'other@church.org'], array_column($contacts, 'value'));
        self::assertNotNull($contacts[0]['endedAt']);
        self::assertNull($contacts[1]['endedAt']);

        $this->call('POST', "/api/people/$a/contacts", $this->admin, ['expectedVersion' => 3, 'kind' => 'EMAIL', 'value' => 'bad address']);
        self::assertSame(400, $this->code());
        $this->call('POST', "/api/people/$a/contacts", $this->admin, ['expectedVersion' => 3, 'kind' => 'FAX', 'value' => '1234']);
        self::assertSame(400, $this->code());
        self::assertSame(3, $this->version('people_persons', $a));
    }

    public function testAddressesAreAddedAndEnded(): void
    {
        $id = $this->register('Solly');

        $this->call('POST', "/api/people/$id/addresses", $this->admin, [
            'expectedVersion' => 0, 'line1' => '1 Church St', 'city' => 'Pretoria', 'postalCode' => '0001', 'countryCode' => 'za',
        ]);
        self::assertSame(201, $this->code());
        $address = (string) $this->json()['id'];
        self::assertSame('ZA', $this->person($id)['addresses'][0]['countryCode']);
        self::assertSame('', $this->person($id)['addresses'][0]['line2']);

        $this->call('POST', "/api/people/$id/addresses", $this->admin, ['expectedVersion' => 1, 'line1' => 'x', 'city' => 'y', 'countryCode' => 'ZAF']);
        self::assertSame(400, $this->code());

        $this->call('POST', "/api/people/$id/addresses/$address/end", $this->admin, ['expectedVersion' => 1]);
        self::assertSame(204, $this->code());
        self::assertNotNull($this->person($id)['addresses'][0]['endedAt']);
        $this->call('POST', "/api/people/$id/addresses/$address/end", $this->admin, ['expectedVersion' => 2]);
        self::assertSame(409, $this->code());
    }

    public function testArchivedPeopleCannotGainContactsOrAddresses(): void
    {
        $id = $this->register('Solly');
        $this->call('PUT', "/api/people/$id/archive", $this->admin, ['expectedVersion' => 0, 'archived' => true]);

        $this->call('POST', "/api/people/$id/contacts", $this->admin, ['expectedVersion' => 1, 'kind' => 'PHONE', 'value' => '0821234567']);
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/people/$id/addresses", $this->admin, ['expectedVersion' => 1, 'line1' => 'x', 'city' => 'y', 'countryCode' => 'ZA']);
        self::assertSame(409, $this->code());
    }

    public function testSearchFiltersPagesAndEscapesWildcards(): void
    {
        $this->register('Solly', ['dateOfBirth' => '1980-01-01'], 'ACTIVE_MEMBER');
        $this->register('Sarah', ['dateOfBirth' => '2015-05-05'], 'VISITOR');
        $this->register('Unknown');
        $this->register('50%_Off');

        $this->call('GET', '/api/people?search=mabasa', $this->reader);
        self::assertSame(['50%_Off', 'Sarah', 'Solly', 'Unknown'], array_column(array_column($this->json(), 'details'), 'firstName'), 'ordered by last name, first name');
        $this->call('GET', '/api/people?search=SAR', $this->reader);
        self::assertSame(['Sarah'], array_column(array_column($this->json(), 'details'), 'firstName'), 'case-insensitive');

        $this->call('GET', '/api/people?search=solly%20maba', $this->reader);
        self::assertCount(1, $this->json(), 'matches the full name');

        $this->call('GET', '/api/people?membershipType=ACTIVE_MEMBER', $this->reader);
        self::assertSame(['Solly'], array_column(array_column($this->json(), 'details'), 'firstName'));

        $this->call('GET', '/api/people?ageCategory=CHILD&asOf=2026-06-01', $this->reader);
        self::assertSame(['Sarah'], array_column(array_column($this->json(), 'details'), 'firstName'));
        $this->call('GET', '/api/people?ageCategory=ADULT&asOf=2026-06-01', $this->reader);
        self::assertSame(['Solly'], array_column(array_column($this->json(), 'details'), 'firstName'));
        $this->call('GET', '/api/people?ageCategory=UNKNOWN', $this->reader);
        self::assertSame(['50%_Off', 'Unknown'], array_column(array_column($this->json(), 'details'), 'firstName'));

        $this->call('GET', '/api/people?search=%25', $this->reader);
        self::assertSame(['50%_Off'], array_column(array_column($this->json(), 'details'), 'firstName'), 'a percent sign is literal');

        $this->call('GET', '/api/people?limit=1&offset=1', $this->reader);
        self::assertCount(1, $this->json());

        foreach (['limit=0', 'limit=101', 'offset=-1', 'membershipType=PROSPECT', 'ageCategory=TEEN', 'asOf=junk'] as $query) {
            $this->call('GET', "/api/people?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
    }

    public function testReadersCannotWriteAndAnonymousCallersAreRejected(): void
    {
        $id = $this->register('Solly');
        $writes = [
            ['POST', '/api/people', ['person' => ['firstName' => 'A', 'lastName' => 'B']]],
            ['PUT', "/api/people/$id", ['expectedVersion' => 0, 'person' => ['firstName' => 'A', 'lastName' => 'B'], 'membershipType' => 'VISITOR']],
            ['PUT', "/api/people/$id/archive", ['expectedVersion' => 0, 'archived' => true]],
            ['POST', '/api/people/titles', ['code' => 'X', 'label' => 'X']],
            ['POST', "/api/people/$id/contacts", ['expectedVersion' => 0, 'kind' => 'PHONE', 'value' => '0821234567']],
            ['POST', "/api/people/$id/addresses", ['expectedVersion' => 0, 'line1' => 'x', 'city' => 'y', 'countryCode' => 'ZA']],
            ['POST', '/api/households', ['name' => 'Family']],
            ['POST', '/api/relationships', ['sourceId' => $id, 'targetId' => $id, 'type' => 'OTHER']],
        ];
        foreach ($writes as [$method, $uri, $body]) {
            $this->call($method, $uri, $this->reader, $body);
            self::assertSame(403, $this->code(), "$method $uri");
            $this->call($method, $uri, null, $body);
            self::assertSame(401, $this->code(), "$method $uri anonymous");
        }
        $this->call('GET', "/api/people/$id", null);
        self::assertSame(401, $this->code());
        self::assertSame(1, $this->rows('people_persons'));
    }

    public function testAccountsWithoutPeoplePermissionsCannotRead(): void
    {
        $this->account('plain');

        foreach (['/api/people', '/api/people/metadata', '/api/households'] as $uri) {
            $this->call('GET', $uri, $this->login('plain'));
            self::assertSame(403, $this->code(), $uri);
        }
    }

    public function testUnknownAndMalformedIdsAreHandled(): void
    {
        $this->call('GET', '/api/people/00000000-0000-4000-8000-0000000000aa', $this->admin);
        self::assertSame(404, $this->code());
        self::assertSame('Person was not found.', $this->message());

        $this->call('GET', '/api/people/not-a-uuid', $this->admin);
        self::assertSame(400, $this->code());

        $this->call('PUT', '/api/people/00000000-0000-4000-8000-0000000000aa/archive', $this->admin, ['expectedVersion' => 0, 'archived' => true]);
        self::assertSame(404, $this->code());
    }

    public function testExpectedVersionIsRequiredOnEveryChange(): void
    {
        $id = $this->register('Solly');
        $bodies = [
            ['PUT', "/api/people/$id", ['person' => ['firstName' => 'A', 'lastName' => 'B'], 'membershipType' => 'VISITOR']],
            ['PUT', "/api/people/$id/archive", ['archived' => true]],
            ['POST', "/api/people/$id/contacts", ['kind' => 'PHONE', 'value' => '0821234567']],
            ['POST', '/api/households', null],
        ];
        foreach ($bodies as [$method, $uri, $body]) {
            if (null === $body) {
                continue;
            }
            $this->call($method, $uri, $this->admin, $body);
            self::assertSame(400, $this->code(), "$method $uri");
        }
        self::assertSame(0, $this->version('people_persons', $id));
    }

    public function testPublishedDirectoriesExposeOnlyWhatOtherContextsNeed(): void
    {
        $id = $this->register('Solly', ['dateOfBirth' => '1980-01-01']);
        $gone = $this->register('Gone', ['lifeStatus' => 'DECEASED', 'dateOfDeath' => '2020-01-01']);
        $this->call('POST', "/api/people/$id/contacts", $this->admin, ['expectedVersion' => 0, 'kind' => 'EMAIL', 'value' => 'solly@church.org']);
        $this->call('POST', "/api/people/$id/contacts", $this->admin, ['expectedVersion' => 1, 'kind' => 'PHONE', 'value' => '0821234567']);

        $store = new DbalPersonStore($this->connection);
        $directory = new PublishedPersonDirectory($store, $store);
        $emails = new PublishedPersonEmailDirectory($store);

        $entry = $directory->get($id);
        self::assertNotNull($entry);
        self::assertSame('Solly Mabasa', $entry->name);
        self::assertSame('1980-01-01', $entry->birthDate?->iso());
        self::assertTrue($entry->available);
        self::assertFalse($directory->get($gone)?->available);
        self::assertNull($directory->get('00000000-0000-4000-8000-0000000000aa'));
        self::assertCount(2, $directory->getAll([$id, $gone]));
        self::assertSame([], $directory->getAll([]));
        self::assertEqualsCanonicalizing([$id], $directory->matchingIds('solly mab'));
        self::assertSame(['solly@church.org'], array_map(static fn ($email): string => $email->value, $emails->active($id)));
    }
}

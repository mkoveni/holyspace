<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Presentation;

/**
 * Volunteer profiles, account links, the credential catalogue, credentials and availability (Java `VolunteerProfiles`,
 * `VolunteerProfilesController`, `VolunteerAccountController`). Java's `ListVolunteersHandlerIntegrationTest` is covered
 * by the live-access checks below; the rest follow the Java source rules.
 */
final class VolunteerProfilesApiTest extends VolunteeringApiTestCase
{
    public function testRegistrationNeedsAnAvailablePersonOnceAndAnActiveAccount(): void
    {
        $this->serving('POST', '/volunteers', ['personId' => self::uuid()]);
        self::assertSame(404, $this->code());
        self::assertSame('Available person was not found.', $this->message());
        $profile = $this->profile();
        $this->serving('POST', '/volunteers', ['personId' => $profile['person']['volunteer']['personId']]);
        self::assertSame(409, $this->code());
        self::assertSame('This person already has a volunteer profile.', $this->message());
        $disabled = $this->account('disabled', status: 'disabled');
        $this->serving('POST', '/volunteers', ['personId' => $this->person('Naledi'), 'accountId' => $disabled]);
        self::assertSame(400, $this->code());
        self::assertSame('Choose an active account.', $this->message());
        $this->serving('POST', '/volunteers', ['personId' => $this->person('Thabo'), 'accountId' => $this->adminId]);
        self::assertSame(409, $this->code());
        self::assertSame('This volunteer, account, name or request is already in use. Refresh before trying again.', $this->message());
        $this->serving('POST', '/volunteers', ['personId' => 'not-a-uuid']);
        self::assertSame(400, $this->code());
        $this->serving('POST', '/volunteers', []);
        self::assertSame(400, $this->code());

        $this->accountWith('coordinator', ['volunteering.manage', 'volunteering.read']);
        $coordinator = $this->login('coordinator');
        $this->serving('POST', '/volunteers', ['personId' => $this->person('Lerato'), 'accountId' => $this->account('lerato')], $coordinator);
        self::assertSame(403, $this->code(), 'linking an account needs identity.manage');
        $this->serving('POST', '/volunteers', ['personId' => $this->person('Sipho')], $coordinator);
        self::assertSame(201, $this->code(), $this->body());
        self::assertSame(2, $this->rows('serving_audit', "action = 'volunteer.registered'"));
    }

    public function testProfilesAreListedSearchedAndShapedLikeJava(): void
    {
        $naledi = $this->add('/volunteers', ['personId' => $this->person('Naledi')]);
        $this->serving('GET', '/volunteers?limit=1');
        self::assertSame(2, $this->json()['total']);
        self::assertSame($naledi, $this->json()['volunteers'][0]['volunteer']['id'], 'newest first');
        self::assertSame(['volunteer', 'name'], array_keys($this->json()['volunteers'][0]));
        self::assertSame(['id', 'personId', 'accountId', 'status', 'availabilityRevision', 'version', 'createdAt', 'updatedAt'], array_keys($this->json()['volunteers'][0]['volunteer']));
        self::assertSame('Naledi Mabasa', $this->json()['volunteers'][0]['name']);
        $this->serving('GET', '/volunteers?search=Solly');
        self::assertSame(1, $this->json()['total']);
        self::assertSame($this->volunteer, $this->json()['volunteers'][0]['volunteer']['id']);
        self::assertSame($this->adminId, $this->json()['volunteers'][0]['volunteer']['accountId']);
        $this->serving('GET', '/volunteers?search=Nobody');
        self::assertSame(['volunteers' => [], 'total' => 0], $this->json());
        foreach (['search='.str_repeat('a', 151), 'limit=0', 'limit=101', 'offset=-1', 'limit=x'] as $query) {
            $this->serving('GET', "/volunteers?$query");
            self::assertSame(400, $this->code(), $query);
        }
        $this->serving('GET', '/volunteers/'.self::uuid());
        self::assertSame(404, $this->code());
        self::assertSame('Volunteer was not found.', $this->message());
        $this->call('GET', '/api/volunteering/volunteers');
        self::assertSame(401, $this->code());
        $this->accountWith('nobody', []);
        $this->serving('GET', '/volunteers', null, $this->login('nobody'));
        self::assertSame(403, $this->code());
        $this->connection->executeStatement("UPDATE user_accounts SET status = 'disabled' WHERE id = ?", [$this->adminId]);
        $this->serving('GET', '/volunteers');
        self::assertSame(401, $this->code(), 'a disabled account loses access at once');
    }

    public function testStatusAndAccountLinksAreVersioned(): void
    {
        $this->serving('PUT', "/volunteers/{$this->volunteer}/status", ['expectedVersion' => 0, 'status' => 'PAUSED']);
        self::assertSame(204, $this->code(), $this->body());
        self::assertSame('PAUSED', $this->profile()['person']['volunteer']['status']);
        $this->serving('PUT', "/volunteers/{$this->volunteer}/status", ['expectedVersion' => 0, 'status' => 'ACTIVE']);
        self::assertSame(409, $this->code());
        $this->serving('PUT', "/volunteers/{$this->volunteer}/status", ['expectedVersion' => 1, 'status' => 'RETIRED']);
        self::assertSame(400, $this->code());
        self::assertSame('Choose active, paused or archived.', $this->message());
        $this->serving('PUT', "/volunteers/{$this->volunteer}/status", ['status' => 'ACTIVE']);
        self::assertSame(400, $this->code(), 'expectedVersion is required');

        $this->serving('PUT', "/volunteers/{$this->volunteer}/account", ['expectedVersion' => 1, 'accountId' => null]);
        self::assertSame(204, $this->code(), $this->body());
        self::assertNull($this->profile()['person']['volunteer']['accountId']);
        $other = $this->add('/volunteers', ['personId' => $this->person('Naledi'), 'accountId' => $this->adminId]);
        $this->serving('PUT', "/volunteers/{$this->volunteer}/account", ['expectedVersion' => 2, 'accountId' => $this->adminId]);
        self::assertSame(409, $this->code(), 'an account links to one volunteer');
        $this->serving('PUT', "/volunteers/$other/account", ['expectedVersion' => 0, 'accountId' => self::uuid()]);
        self::assertSame(400, $this->code());
        self::assertSame('Choose an active account.', $this->message());

        $this->accountWith('coordinator', ['volunteering.manage']);
        $this->serving('PUT', "/volunteers/$other/account", ['expectedVersion' => 0, 'accountId' => null], $this->login('coordinator'));
        self::assertSame(403, $this->code());
        self::assertSame(1, $this->rows('serving_audit', "action = 'volunteer.account.changed'"));
        self::assertSame(1, $this->rows('serving_audit', "action = 'volunteer.status'"));
    }

    public function testCredentialTypesAreACatalogueOfUniqueLowercaseCodes(): void
    {
        $this->serving('GET', '/credential-types');
        self::assertSame([['id' => $this->type, 'code' => 'screening', 'name' => 'Screening']], $this->json());
        $this->serving('POST', '/credential-types', ['code' => 'screening', 'name' => 'Again']);
        self::assertSame(409, $this->code());
        $this->serving('POST', '/credential-types', ['code' => 'First Aid', 'name' => 'First aid']);
        self::assertSame(400, $this->code());
        self::assertSame('Use a lowercase credential code.', $this->message());
        $this->serving('POST', '/credential-types', ['code' => str_repeat('a', 81), 'name' => 'Long']);
        self::assertSame(400, $this->code());
        $this->accountWith('coordinator', ['volunteering.manage', 'volunteering.read']);
        $this->serving('POST', '/credential-types', ['code' => 'first-aid', 'name' => 'First aid'], $this->login('coordinator'));
        self::assertSame(403, $this->code(), 'the catalogue needs volunteering.credentials.manage');
    }

    public function testCredentialsAreRecordedAndRevokedWithoutExposingEvidence(): void
    {
        $this->serving('POST', "/volunteers/{$this->volunteer}/credentials", ['expectedVersion' => 0, 'typeId' => self::uuid(), 'validFrom' => self::at($this->validFrom), 'validUntil' => self::at($this->validUntil), 'evidence' => 'Seen']);
        self::assertSame(404, $this->code());
        self::assertSame('Credential type was not found.', $this->message());
        $this->serving('POST', "/volunteers/{$this->volunteer}/credentials", ['expectedVersion' => 0, 'typeId' => $this->type, 'validFrom' => self::at($this->validUntil), 'validUntil' => self::at($this->validFrom), 'evidence' => 'Seen']);
        self::assertSame(400, $this->code());
        self::assertSame('The end must follow the start.', $this->message());
        $credential = $this->add("/volunteers/{$this->volunteer}/credentials", ['expectedVersion' => 0, 'typeId' => $this->type, 'validFrom' => self::at($this->validFrom), 'validUntil' => self::at($this->validUntil), 'evidence' => 'Police clearance 1234']);
        $this->serving('POST', "/volunteers/{$this->volunteer}/credentials", ['expectedVersion' => 0, 'typeId' => $this->type, 'validFrom' => self::at($this->validFrom), 'validUntil' => self::at($this->validUntil), 'evidence' => 'Stale']);
        self::assertSame(409, $this->code());
        $profile = $this->profile();
        self::assertSame(1, $profile['person']['volunteer']['version']);
        self::assertSame([['id' => $credential, 'typeId' => $this->type, 'type' => 'Screening', 'validFrom' => self::at($this->validFrom), 'validUntil' => self::at($this->validUntil), 'revokedAt' => null]], $profile['credentials']);
        self::assertStringNotContainsString('Police clearance', $this->body());

        $this->serving('POST', "/volunteers/{$this->volunteer}/credentials/revoke", ['expectedVersion' => 1, 'credentialId' => self::uuid(), 'reason' => 'Mistake']);
        self::assertSame(404, $this->code());
        self::assertSame('Credential was not found.', $this->message());
        $this->serving('POST', "/volunteers/{$this->volunteer}/credentials/revoke", ['expectedVersion' => 1, 'credentialId' => $credential, 'reason' => '']);
        self::assertSame(400, $this->code());
        $this->serving('POST', "/volunteers/{$this->volunteer}/credentials/revoke", ['expectedVersion' => 1, 'credentialId' => $credential, 'reason' => 'Expired clearance']);
        self::assertSame(204, $this->code(), $this->body());
        self::assertNotNull($this->profile()['credentials'][0]['revokedAt']);
        self::assertSame('Expired clearance', $this->connection->fetchOne("SELECT reason FROM serving_audit WHERE action = 'credential.revoked'"));
        self::assertSame(1, $this->rows('serving_audit', "action = 'credential.verified'"));
    }

    public function testAvailabilityIsReplacedAsANewRevision(): void
    {
        $weekly = ['dayOfWeek' => 7, 'startsAt' => '09:00', 'endsAt' => '12:30:15', 'timeZone' => 'Africa/Johannesburg', 'validFrom' => '2026-01-01', 'validUntil' => '2027-01-01'];
        $exception = ['startsAt' => self::at($this->start), 'endsAt' => self::at($this->end), 'available' => false];
        $this->serving('PUT', "/volunteers/{$this->volunteer}/availability", ['expectedVersion' => 0, 'weekly' => [$weekly], 'exceptions' => [$exception]]);
        self::assertSame(204, $this->code(), $this->body());
        $profile = $this->profile();
        self::assertSame(1, $profile['person']['volunteer']['availabilityRevision']);
        self::assertSame(['id', 'volunteerId', 'revision', 'dayOfWeek', 'startsAt', 'endsAt', 'timeZone', 'validFrom', 'validUntil'], array_keys($profile['weekly'][0]));
        self::assertSame([7, '09:00', '12:30:15', 1], [$profile['weekly'][0]['dayOfWeek'], $profile['weekly'][0]['startsAt'], $profile['weekly'][0]['endsAt'], $profile['weekly'][0]['revision']]);
        self::assertSame(['id', 'volunteerId', 'revision', 'startsAt', 'endsAt', 'available'], array_keys($profile['exceptions'][0]));

        $this->serving('PUT', "/volunteers/{$this->volunteer}/availability", ['expectedVersion' => 1, 'weekly' => [], 'exceptions' => []]);
        self::assertSame(204, $this->code(), $this->body());
        self::assertSame([[], []], [$this->profile()['weekly'], $this->profile()['exceptions']]);
        self::assertSame(1, $this->rows('serving_availability_rules'), 'earlier revisions stay as history');
        $this->serving('PUT', "/volunteers/{$this->volunteer}/availability", ['expectedVersion' => 2, 'weekly' => array_fill(0, 101, $weekly), 'exceptions' => []]);
        self::assertSame(400, $this->code());
        self::assertSame('Use at most 100 weekly rules and 100 exceptions.', $this->message());
        $this->serving('PUT', "/volunteers/{$this->volunteer}/availability", ['expectedVersion' => 2, 'weekly' => [[...$weekly, 'endsAt' => '09:00']], 'exceptions' => []]);
        self::assertSame(400, $this->code());
        self::assertSame(2, $this->rows('serving_audit', "action = 'volunteer.availability'"));
    }
}

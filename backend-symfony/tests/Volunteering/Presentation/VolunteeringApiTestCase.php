<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

/**
 * Port of Java `VolunteerIntegrationTestSupport`: an administrator (who holds every volunteering permission) linked to
 * volunteer Solly, a Welcome team with a Welcomer role requiring Screening, and a two-position shift starting tomorrow.
 */
abstract class VolunteeringApiTestCase extends IdentityApiTestCase
{
    protected string $admin;

    protected string $adminId;

    protected string $volunteer;

    protected string $team;

    protected string $role;

    protected string $shift;

    protected string $type;

    protected DateTimeImmutable $start;

    protected DateTimeImmutable $end;

    protected DateTimeImmutable $validFrom;

    protected DateTimeImmutable $validUntil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
        $this->start = new DateTimeImmutable('@'.(time() + 86400));
        $this->end = $this->start->modify('+3600 seconds');
        $this->validFrom = $this->start->modify('-86400 seconds');
        $this->validUntil = $this->end->modify('+86400 seconds');
        $this->volunteer = $this->add('/volunteers', ['personId' => $this->person('Solly'), 'accountId' => $this->adminId]);
        $this->team = $this->add('/teams', ['name' => 'Welcome team']);
        $this->type = $this->add('/credential-types', ['code' => 'screening', 'name' => 'Screening']);
        $this->role = $this->add("/teams/{$this->team}/roles", ['expectedVersion' => 0, 'name' => 'Welcomer', 'childrenRole' => 'NONE', 'requiredCredentials' => [$this->type]]);
        $this->shift = $this->add('/shifts', $this->draft(self::uuid(), $this->start, $this->end, 2));
    }

    protected static function at(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    protected static function uuid(): string
    {
        return Uuid::v4()->toRfc4122();
    }

    protected function person(string $firstName): string
    {
        $this->call('POST', '/api/people', $this->admin, ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->body());

        return (string) $this->json()['id'];
    }

    /** @param array<array-key, mixed>|null $body */
    protected function serving(string $method, string $path, ?array $body = null, ?string $token = null): void
    {
        $this->call($method, '/api/volunteering'.$path, $token ?? $this->admin, $body);
    }

    /** @param array<array-key, mixed> $body */
    protected function add(string $path, array $body): string
    {
        $this->serving('POST', $path, $body);
        self::assertSame(201, $this->code(), $path.': '.$this->client->getResponse()->getContent());

        return (string) $this->json()['id'];
    }

    /** @return array<string, mixed> */
    protected function draft(string $key, DateTimeImmutable $from, DateTimeImmutable $until, int $positions): array
    {
        return ['requestId' => $key, 'teamId' => $this->team, 'roleId' => $this->role, 'sourceKind' => 'STANDALONE', 'sourceId' => null, 'startsAt' => self::at($from), 'endsAt' => self::at($until), 'positions' => $positions];
    }

    /** @return array<string|int, mixed> */
    protected function shiftDetails(string $id): array
    {
        $this->serving('GET', "/shifts/$id");
        self::assertSame(200, $this->code(), $this->body());

        return $this->json();
    }

    protected function shiftVersion(string $id): int
    {
        return (int) $this->shiftDetails($id)['shift']['version'];
    }

    protected function teamVersion(): int
    {
        $this->serving('GET', "/teams/{$this->team}");

        return (int) $this->json()['team']['version'];
    }

    /** @return array<string|int, mixed> */
    protected function profile(?string $volunteer = null): array
    {
        $this->serving('GET', '/volunteers/'.($volunteer ?? $this->volunteer));
        self::assertSame(200, $this->code(), $this->body());

        return $this->json();
    }

    protected function volunteerVersion(): int
    {
        return (int) $this->profile()['person']['volunteer']['version'];
    }

    /** Membership, approval, credential and availability covering the shift. */
    protected function ready(): void
    {
        $this->add("/teams/{$this->team}/memberships", ['expectedVersion' => $this->teamVersion(), 'volunteerId' => $this->volunteer, 'validFrom' => self::at($this->validFrom), 'validUntil' => self::at($this->validUntil)]);
        $this->add("/teams/{$this->team}/approvals", ['expectedVersion' => $this->teamVersion(), 'volunteerId' => $this->volunteer, 'roleId' => $this->role, 'validFrom' => self::at($this->validFrom), 'validUntil' => self::at($this->validUntil)]);
        $this->add("/volunteers/{$this->volunteer}/credentials", ['expectedVersion' => $this->volunteerVersion(), 'typeId' => $this->type, 'validFrom' => self::at($this->validFrom), 'validUntil' => self::at($this->validUntil), 'evidence' => 'Verified source / 2026']);
        $this->serving('PUT', "/volunteers/{$this->volunteer}/availability", ['expectedVersion' => $this->volunteerVersion(), 'weekly' => [], 'exceptions' => [['startsAt' => self::at($this->validFrom), 'endsAt' => self::at($this->validUntil), 'available' => true]]]);
        self::assertSame(204, $this->code(), $this->body());
    }

    protected function book(string $volunteer, string $status): string
    {
        return $this->add('/assignments', ['requestId' => self::uuid(), 'shiftId' => $this->shift, 'expectedVersion' => $this->shiftVersion($this->shift), 'volunteerId' => $volunteer, 'status' => $status]);
    }

    protected function invite(string $assignment, int $version = 0): string
    {
        return $this->add("/assignments/$assignment/invitations", ['expectedVersion' => $version, 'expiresAt' => self::at($this->start)]);
    }

    protected function readerToken(): string
    {
        $this->accountWith('reader', ['volunteering.read']);

        return $this->login('reader');
    }

    protected function body(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }
}

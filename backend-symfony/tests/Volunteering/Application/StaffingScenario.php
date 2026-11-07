<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application;

use App\Volunteering\Application\ActivityRegistry;
use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\CredentialTypeView;
use App\Volunteering\Application\Contract\RoleView;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Application\Contract\SourceActivity;
use App\Volunteering\Application\Contract\TeamView;
use App\Volunteering\Application\Contract\VolunteerView;
use App\Volunteering\Application\Fingerprint;
use App\Volunteering\Application\PublishedStaffingDirectory;
use App\Volunteering\Application\VolunteerReadiness;
use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\ValidityWindow;
use DateTimeImmutable;

/** A fully ready CONFIRMED assignment on a CHILDREN_SESSION shift; tests break one thing at a time. */
final class StaffingScenario
{
    public const VOLUNTEER = '00000000-0000-4000-8000-000000000001';
    public const PERSON = '00000000-0000-4000-8000-000000000002';
    public const TEAM = '00000000-0000-4000-8000-000000000003';
    public const ROLE = '00000000-0000-4000-8000-000000000004';
    public const SHIFT = '00000000-0000-4000-8000-000000000005';
    public const ASSIGNMENT = '00000000-0000-4000-8000-000000000006';
    public const SESSION = '00000000-0000-4000-8000-000000000007';
    public const CREDENTIAL_TYPE = '00000000-0000-4000-8000-000000000008';

    public FakeStaffingData $data;

    public FakeActivitySource $source;

    public DateTimeImmutable $from;

    public DateTimeImmutable $until;

    /** @var array<string, bool> */
    public array $people = [self::PERSON => true];

    public function __construct()
    {
        $this->from = new DateTimeImmutable('2026-11-08T08:00:00Z');
        $this->until = new DateTimeImmutable('2026-11-08T10:00:00Z');
        $this->source = new FakeActivitySource('CHILDREN_SESSION', [
            self::SESSION => new SourceActivity(self::SESSION, 'Sunday', $this->from, $this->until, true),
        ]);
        $window = new ValidityWindow($this->from->modify('-1 day'), $this->until->modify('+1 day'));
        $data = new FakeStaffingData();
        $data->volunteers[self::VOLUNTEER] = new VolunteerView(self::VOLUNTEER, self::PERSON, 'ACTIVE', 0);
        $data->teams[self::TEAM] = new TeamView(self::TEAM, true);
        $data->roles[self::ROLE] = new RoleView(self::ROLE, self::TEAM, 'LEADER', true, []);
        $data->memberships = [$window];
        $data->approvals = [$window];
        $data->exceptions = [new AvailabilityException($this->from, $this->until, true)];
        $data->shifts[self::SHIFT] = new ShiftView(
            self::SHIFT,
            self::TEAM,
            self::ROLE,
            'CHILDREN_SESSION',
            self::SESSION,
            Fingerprint::of(self::SESSION, Fingerprint::instant($this->from), Fingerprint::instant($this->until)),
            $this->from,
            $this->until,
            'OPEN',
            1,
        );
        $data->assignments[self::ASSIGNMENT] = new AssignmentView(self::ASSIGNMENT, self::SHIFT, self::VOLUNTEER, 'CONFIRMED');
        $data->credentialTypes = [new CredentialTypeView(self::CREDENTIAL_TYPE, 'Police clearance')];
        $this->data = $data;
    }

    public function readiness(): VolunteerReadiness
    {
        return new VolunteerReadiness($this->data, $this->data, $this->data, new FakeDirectory($this->people), new ActivityRegistry([$this->source]));
    }

    public function directory(): PublishedStaffingDirectory
    {
        return new PublishedStaffingDirectory($this->data, $this->data, $this->data, $this->readiness(), new FakeDirectory($this->people));
    }

    public function shift(): ShiftView
    {
        return $this->data->shifts[self::SHIFT];
    }
}

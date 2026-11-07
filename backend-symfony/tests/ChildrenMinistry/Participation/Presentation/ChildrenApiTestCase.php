<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Participation\Presentation;

use App\Shared\Domain\CalendarDate;
use App\Tests\Support\IdentityApiTestCase;
use DateTimeImmutable;
use DateTimeZone;

/** An administrator (holds every children.* permission) and a reader with children.read only. */
abstract class ChildrenApiTestCase extends IdentityApiTestCase
{
    protected string $adminId;
    protected string $admin;
    protected string $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
        $this->accountWith('reader', ['children.read']);
        $this->reader = $this->login('reader');
    }

    /** Today's date in the church's time zone, the same day the application uses. */
    protected function today(string $modify = ''): string
    {
        $day = new DateTimeImmutable('today', new DateTimeZone('Africa/Johannesburg'));

        return ('' === $modify ? $day : $day->modify($modify))->format('Y-m-d');
    }

    /** The date N whole years before today, clamping 29 February like the application does (PHP's modify() would roll to 1 March). */
    protected function yearsAgo(int $years, int $plusDays = 0): string
    {
        $date = CalendarDate::parse($this->today())->minusYears($years);

        return ($plusDays > 0 ? $date->plusDays($plusDays) : $date)->iso();
    }

    /** A person registered through the People API; the birth date is optional. */
    protected function person(string $firstName, ?string $dateOfBirth): string
    {
        $person = ['firstName' => $firstName, 'lastName' => 'Mabasa'];
        if (null !== $dateOfBirth) {
            $person['dateOfBirth'] = $dateOfBirth;
        }
        $this->call('POST', '/api/people', $this->admin, ['person' => $person]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    protected function programme(string $name = 'Sunday children'): string
    {
        return $this->create('/programmes', ['name' => $name, 'minStaff' => 1, 'childrenPerStaff' => 2]);
    }

    protected function enrol(string $programme, string $child, ?string $from = null, ?string $until = null): string
    {
        return $this->create('/enrolments', ['programmeId' => $programme, 'childId' => $child, 'validFrom' => $from ?? $this->today('-1 day'), 'validUntil' => $until ?? $this->today('+1 year')]);
    }

    /** @param array<string, mixed> $body */
    protected function create(string $path, array $body): string
    {
        $this->call('POST', '/api/children'.$path, $this->admin, $body);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }
}

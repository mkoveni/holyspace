<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/** An events manager, an events reader, a venue, and helpers to plan events a year ahead. */
abstract class EventsApiTestCase extends IdentityApiTestCase
{
    protected string $manager;
    protected string $reader;
    protected string $registrar;
    protected string $registrationReader;
    protected string $directory;
    protected string $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('manager', ['events.manage']);
        $this->accountWith('reader', ['events.read']);
        $this->manager = $this->login('manager');
        $this->reader = $this->login('reader');
        $this->accountWith('registrar', ['events.registrations.manage', 'events.read']);
        $this->accountWith('regreader', ['events.registrations.read']);
        $this->accountWith('directory', ['people.manage']);
        $this->registrar = $this->login('registrar');
        $this->registrationReader = $this->login('regreader');
        $this->directory = $this->login('directory');
        $this->venue = $this->venue('Main hall');
    }

    protected function venue(string $name, bool $exclusive = true): string
    {
        $this->call('POST', '/api/events/venues', $this->manager, ['name' => $name, 'address' => '', 'exclusive' => $exclusive]);

        return (string) $this->json()['id'];
    }

    protected function nextYear(string $monthDay = '03-15'): string
    {
        return (new DateTimeImmutable('now'))->modify('+1 year')->format('Y').'-'.$monthDay;
    }

    /** @return array<string, mixed> */
    protected function draft(string $first = '', int $count = 3, ?string $venue = null, string $frequency = 'WEEKLY'): array
    {
        return [
            'venueId' => $venue ?? $this->venue,
            'timeZone' => 'Africa/Johannesburg',
            'frequency' => $frequency,
            'interval' => 1,
            'count' => $count,
            'firstDate' => '' === $first ? $this->nextYear() : $first,
            'startsAt' => '10:00',
            'endsAt' => '11:30',
            'endDayOffset' => 0,
        ];
    }

    /** @param array<string, mixed> $draft */
    protected function hash(array $draft): string
    {
        $this->call('POST', '/api/events/schedule/preview', $this->reader, ['draft' => $draft]);
        self::assertSame(200, $this->code(), $this->message());

        return (string) $this->json()['hash'];
    }

    /** @return array<string, mixed> */
    /**
     * @param array<string, mixed> $draft
     *
     * @return array<string, mixed>
     */
    protected function body(array $draft, string $request, ?string $hash = null): array
    {
        return ['requestId' => $request, 'title' => 'Sunday service', 'type' => 'SERVICE', 'description' => '', 'schedule' => $draft, 'previewHash' => $hash ?? $this->hash($draft)];
    }

    /** @param array<string, mixed>|null $draft */
    protected function event(?array $draft = null): string
    {
        $this->call('POST', '/api/events', $this->manager, $this->body($draft ?? $this->draft(), Uuid::v4()->toRfc4122()));
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    protected function person(string $firstName): string
    {
        $this->call('POST', '/api/people', $this->directory, ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }
}

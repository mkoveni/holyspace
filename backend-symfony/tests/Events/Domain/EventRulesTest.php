<?php

declare(strict_types=1);

namespace App\Tests\Events\Domain;

use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventRulesTest extends TestCase
{
    public function testTextIsTrimmedAndBounded(): void
    {
        self::assertSame('Hall', EventRules::text('  Hall ', 7));
        $this->rejects(static fn () => EventRules::text('Hall!', 4), 'Enter text of 1–4 characters.');
        $this->rejects(static fn () => EventRules::text('   ', 4), 'Enter text of 1–4 characters.');
        $this->rejects(static fn () => EventRules::text(null, 4), 'Enter text of 1–4 characters.');
    }

    public function testOptionalTextBecomesEmptyWhenBlank(): void
    {
        self::assertSame('', EventRules::optional(null, 4));
        self::assertSame('', EventRules::optional('  ', 4));
        self::assertSame('Hall', EventRules::optional(' Hall', 5));
        $this->rejects(static fn () => EventRules::optional('Hall!', 4), 'Enter text of 1–4 characters.');
    }

    public function testAnIdentifierIsRequired(): void
    {
        self::assertSame('x', EventRules::id('x'));
        $this->rejects(static fn () => EventRules::id(null), 'An identifier is required.');
        $this->rejects(static fn () => EventRules::id(''), 'An identifier is required.');
    }

    /** @return iterable<string, array{int, int, bool}> */
    public static function pages(): iterable
    {
        yield 'first page' => [1, 0, true];
        yield 'largest page' => [100, 100000, true];
        yield 'zero limit' => [0, 0, false];
        yield 'limit too large' => [101, 0, false];
        yield 'negative offset' => [10, -1, false];
        yield 'offset too large' => [10, 100001, false];
    }

    #[DataProvider('pages')]
    public function testPageBounds(int $limit, int $offset, bool $valid): void
    {
        if ($valid) {
            EventRules::page($limit, $offset);
            $this->addToAssertionCount(1);

            return;
        }
        $this->rejects(static fn () => EventRules::page($limit, $offset), 'Choose a page of 1–100 records.');
    }

    public function testPeriodMustBePositiveAndAtMostSevenDays(): void
    {
        $from = new DateTimeImmutable('2026-11-01T00:00:00Z');
        EventRules::period($from, $from->modify('+7 days'));
        EventRules::period($from, $from->modify('+1 microsecond'));
        $this->addToAssertionCount(2);
        $message = 'Choose a positive interval of at most seven days.';
        $this->rejects(static fn () => EventRules::period($from, $from->modify('+7 days +1 microsecond')), $message);
        $this->rejects(static fn () => EventRules::period($from, $from), $message);
        $this->rejects(static fn () => EventRules::period($from, $from->modify('-1 second')), $message);
    }

    public function testRangeMustBePositiveAndAtMost366Days(): void
    {
        $from = new DateTimeImmutable('2026-01-01T00:00:00Z');
        EventRules::range($from, $from->modify('+366 days'));
        $this->addToAssertionCount(1);
        $message = 'Choose a date range of at most 366 days.';
        $this->rejects(static fn () => EventRules::range($from, $from->modify('+366 days +1 microsecond')), $message);
        $this->rejects(static fn () => EventRules::range($from, $from), $message);
    }

    public function testOverlapIsHalfOpen(): void
    {
        $nine = new DateTimeImmutable('2026-11-01T09:00:00Z');
        $ten = new DateTimeImmutable('2026-11-01T10:00:00Z');
        $eleven = new DateTimeImmutable('2026-11-01T11:00:00Z');

        self::assertTrue(EventRules::overlaps($nine, $eleven, $ten, $eleven));
        self::assertFalse(EventRules::overlaps($nine, $ten, $ten, $eleven));
        self::assertFalse(EventRules::overlaps($ten, $eleven, $nine, $ten));
    }

    public function testVersionMismatchIsAConflict(): void
    {
        EventRules::version(3, 3);
        $this->addToAssertionCount(1);
        try {
            EventRules::version(4, 3);
            self::fail('A stale version must conflict.');
        } catch (EventException $error) {
            self::assertSame(ErrorKind::Conflict, $error->kind());
            self::assertSame('These details changed. Refresh and review before saving.', $error->getMessage());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function validZones(): iterable
    {
        yield 'region' => ['Europe/London'];
        yield 'utc' => ['UTC'];
        yield 'zulu' => ['Z'];
        yield 'offset' => ['+02:00'];
    }

    #[DataProvider('validZones')]
    public function testZoneAcceptsWhatJavaZoneIdAccepts(string $zone): void
    {
        self::assertNotSame('', EventRules::zone($zone)->getName());
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidZones(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'abbreviation' => ['PDT'];
        yield 'wrong case' => ['europe/london'];
        yield 'nonsense' => ['Mars/Olympus'];
    }

    #[DataProvider('invalidZones')]
    public function testZoneRejectsEverythingElse(?string $zone): void
    {
        $this->rejects(static fn () => EventRules::zone($zone), 'Choose a valid time zone.');
    }

    public function testInstantsAreNormalisedToUtc(): void
    {
        $at = EventRules::instant(new DateTimeImmutable('2026-11-01T12:00:00.123456+02:00'));

        self::assertSame('2026-11-01T10:00:00.123456+00:00', $at->format('Y-m-d\TH:i:s.uP'));
    }

    public function testExceptionKindsMapToTheirMessages(): void
    {
        self::assertSame(ErrorKind::NotFound, EventException::missing('Venue')->kind());
        self::assertSame('Venue was not found.', EventException::missing('Venue')->getMessage());
        self::assertSame(ErrorKind::Invalid, EventException::invalid('x')->kind());
    }

    /** @param callable(): mixed $action */
    private function rejects(callable $action, string $message): void
    {
        try {
            $action();
            self::fail('Expected an EventException.');
        } catch (EventException $error) {
            self::assertSame(ErrorKind::Invalid, $error->kind());
            self::assertSame($message, $error->getMessage());
        }
    }
}

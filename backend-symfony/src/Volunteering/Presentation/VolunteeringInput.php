<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation;

use App\Shared\Presentation\Json;
use App\Volunteering\Application\Contract\ShiftDraft;
use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Hand-read request bodies (lists and records Java bound without constraints): wrong JSON types are a 400 here, missing
 * values reach the domain, which names the rule they break (Java's primitive ints default to 0, booleans to false).
 */
final class VolunteeringInput
{
    private function __construct()
    {
    }

    /** @param array<array-key, mixed> $body */
    public static function draft(array $body): ShiftDraft
    {
        return new ShiftDraft(
            self::uuid($body, 'requestId'),
            self::uuid($body, 'teamId'),
            self::uuid($body, 'roleId'),
            self::string($body, 'sourceKind'),
            self::uuid($body, 'sourceId'),
            self::instant($body, 'startsAt'),
            self::instant($body, 'endsAt'),
            self::int($body, 'positions'),
        );
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return list<ShiftDraft|null>
     */
    public static function drafts(array $body): array
    {
        if (!array_is_list($body)) {
            throw new BadRequestException('Send a list of shift drafts.');
        }

        return array_map(static fn (mixed $item): ?ShiftDraft => null === $item ? null : self::draft(self::record($item)), $body);
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return list<AvailabilityRule>|null
     */
    public static function weekly(array $body): ?array
    {
        $items = self::list($body, 'weekly');

        return null === $items ? null : array_map(static function (mixed $item): AvailabilityRule {
            $rule = self::record($item);

            return new AvailabilityRule(
                self::int($rule, 'dayOfWeek'),
                self::localTime($rule, 'startsAt') ?? '',
                self::localTime($rule, 'endsAt') ?? '',
                self::string($rule, 'timeZone') ?? '',
                self::localDate($rule, 'validFrom') ?? '',
                self::localDate($rule, 'validUntil') ?? '',
            );
        }, $items);
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return list<AvailabilityException>|null
     */
    public static function exceptions(array $body): ?array
    {
        $items = self::list($body, 'exceptions');

        return null === $items ? null : array_map(static function (mixed $item): AvailabilityException {
            $window = self::record($item);
            $available = $window['available'] ?? false;
            if (!\is_bool($available)) {
                throw new BadRequestException('available must be a boolean.');
            }

            return new AvailabilityException(
                self::instant($window, 'startsAt') ?? throw VolunteerException::invalid('A timestamp is required.'),
                self::instant($window, 'endsAt') ?? throw VolunteerException::invalid('A timestamp is required.'),
                $available,
            );
        }, $items);
    }

    /** @param array<array-key, mixed> $body */
    public static function expectedVersion(array $body): int
    {
        $version = $body['expectedVersion'] ?? null;
        if (!\is_int($version) || $version < 0) {
            throw new BadRequestException('expectedVersion is required.');
        }

        return $version;
    }

    /** @param array<array-key, mixed> $body */
    public static function uuid(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;
        if (null !== $value && !\is_string($value)) {
            throw new BadRequestException($key.' must be an identifier.');
        }

        return null === $value ? null : Json::uuid($value);
    }

    /** @param array<array-key, mixed> $body */
    public static function string(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;
        if (null !== $value && !\is_string($value)) {
            throw new BadRequestException($key.' must be text.');
        }

        return $value;
    }

    /** @param array<array-key, mixed> $body */
    public static function int(array $body, string $key): int
    {
        $value = $body[$key] ?? 0;
        if (!\is_int($value)) {
            throw new BadRequestException($key.' must be a whole number.');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return list<string|null>|null
     */
    public static function dates(array $body, string $key): ?array
    {
        $items = self::list($body, $key);

        return null === $items ? null : array_map(static fn (mixed $item): ?string => null === $item ? null : self::date($item), $items);
    }

    /**
     * `HH:mm`, `HH:mm:ss` or with a fraction, as Java's `LocalTime` reads it, normalised to `HH:mm:ss`.
     *
     * @param array<array-key, mixed> $body
     */
    public static function localTime(array $body, string $key): ?string
    {
        $value = self::string($body, $key);
        if (null === $value) {
            return null;
        }
        if (1 !== preg_match('/\A([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d)(?:\.\d{1,9})?)?\z/', $value, $parts)) {
            throw new BadRequestException($key.' must be a local time.');
        }

        return $parts[1].':'.$parts[2].':'.($parts[3] ?? '00');
    }

    /** @param array<array-key, mixed> $body */
    private static function localDate(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return null === $value ? null : self::date($value);
    }

    private static function date(mixed $value): string
    {
        if (!\is_string($value) || 1 !== preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new BadRequestException('Dates must use the ISO-8601 format.');
        }

        return $value;
    }

    /** @param array<array-key, mixed> $body */
    private static function instant(array $body, string $key): ?DateTimeImmutable
    {
        $value = self::string($body, $key);

        return null === $value ? null : Json::parseInstant($value);
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return list<mixed>|null
     */
    private static function list(array $body, string $key): ?array
    {
        $value = $body[$key] ?? null;
        if (null !== $value && (!\is_array($value) || !array_is_list($value))) {
            throw new BadRequestException($key.' must be a list.');
        }

        return $value;
    }

    /** @return array<array-key, mixed> */
    private static function record(mixed $item): array
    {
        if (!\is_array($item) || ([] !== $item && array_is_list($item))) {
            throw new BadRequestException('Each item must be an object.');
        }

        return $item;
    }
}

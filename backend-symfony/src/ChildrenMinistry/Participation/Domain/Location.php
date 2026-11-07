<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateTimeZone;
use Exception;

/** A campus or venue. Its IANA time zone decides the local calendar day of every session held there. */
final readonly class Location
{
    public string $name;

    private function __construct(public string $id, string $name, public string $timeZone)
    {
        $this->name = ChildrenRules::text($name, 120);
        try {
            new DateTimeZone($timeZone);
        } catch (Exception) {
            throw ChildrenException::invalid('Select a valid IANA time zone.');
        }
    }

    public static function create(string $id, string $name, string $timeZone): self
    {
        return new self($id, $name, $timeZone);
    }

    public static function reconstitute(string $id, string $name, string $timeZone): self
    {
        return new self($id, $name, $timeZone);
    }
}

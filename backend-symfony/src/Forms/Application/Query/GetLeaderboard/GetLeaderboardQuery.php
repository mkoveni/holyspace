<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetLeaderboard;

use SensitiveParameter;

/** A public link token (anonymous caller) or a form id with the signed-in account asking. */
final readonly class GetLeaderboardQuery
{
    private function __construct(public ?string $token, public ?string $formId, public ?string $accountId)
    {
    }

    public static function forPublic(#[SensitiveParameter] string $token): self
    {
        return new self($token, null, null);
    }

    public static function forMember(string $accountId, string $formId): self
    {
        return new self(null, $formId, $accountId);
    }
}

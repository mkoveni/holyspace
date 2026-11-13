<?php

declare(strict_types=1);

namespace App\Forms\Application\Contract;

use DateTimeImmutable;

/** Keyed hashes and the page challenge. Secrets never leave the implementation. */
interface FormKeys
{
    public function tokenHash(string $token): string;

    /** A new random public link token (high entropy; shown to staff once). */
    public function newToken(): string;

    /** A fixed-width key for a client's retry id, so retries are idempotent without storing the raw value. */
    public function submissionKey(string $formId, string $clientKey): string;

    /** The participation mark of an account on an anonymous form: it cannot be joined back to a response. */
    public function participationMark(string $formId, string $accountId): string;

    public function challenge(string $formId, DateTimeImmutable $now): string;

    public function challengeIsValid(string $formId, string $challenge, DateTimeImmutable $now): bool;
}

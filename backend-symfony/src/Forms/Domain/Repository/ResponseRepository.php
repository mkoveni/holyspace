<?php

declare(strict_types=1);

namespace App\Forms\Domain\Repository;

use App\Forms\Domain\FormResponse;
use DateTimeImmutable;

interface ResponseRepository
{
    public function count(string $formId): int;

    /** Whether a response with this submission key exists (an idempotent retry). */
    public function hasSubmission(string $formId, string $submissionKey): bool;

    /** Whether this account already answered an identified form. */
    public function hasAccountResponse(string $formId, string $accountId): bool;

    public function insert(FormResponse $response): void;

    /** Records that this participant answered an anonymous form; false when the mark already exists. */
    public function insertMark(string $formId, string $markHash): bool;

    /**
     * Hard-deletes every response whose retention has ended and the participation marks of forms left without responses.
     *
     * @return array<string, int> responses deleted, by form id
     */
    public function deleteExpired(DateTimeImmutable $today): array;
}

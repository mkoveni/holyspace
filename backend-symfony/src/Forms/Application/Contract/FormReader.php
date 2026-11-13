<?php

declare(strict_types=1);

namespace App\Forms\Application\Contract;

use DateTimeImmutable;

interface FormReader
{
    /** @return list<FormSummary> newest first */
    public function list(int $limit, int $offset): array;

    public function find(string $id): ?FormView;

    /** Published, open-for-viewing form behind a public link token hash. */
    public function publishedByTokenHash(string $tokenHash, DateTimeImmutable $now): ?PublishedForm;

    public function published(string $id, DateTimeImmutable $now): ?PublishedForm;

    /** @return list<PublishedForm> every published non-public form that has started, for the caller's audience filter */
    public function internalPublished(DateTimeImmutable $now): array;

    /** @return list<ResponseView> by date, then id */
    public function responses(string $formId, int $limit, int $offset): array;

    public function responseCount(string $formId): int;

    /** @return list<array<string, mixed>> every stored answer set of the form, for summaries (bounded by max_responses or retention) */
    public function answerSets(string $formId): array;

    /** @return list<LeaderboardEntry> best score first, then earliest date, then id; hidden entries skipped */
    public function leaderboard(string $formId, int $size): array;

    public function hideFromLeaderboard(string $formId, string $responseId): bool;

    /** @return array<string, mixed>|null the stored definition of a published version, with its answer key */
    public function publishedDefinition(string $formId, int $version): ?array;
}

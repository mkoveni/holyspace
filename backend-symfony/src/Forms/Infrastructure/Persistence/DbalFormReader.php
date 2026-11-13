<?php

declare(strict_types=1);

namespace App\Forms\Infrastructure\Persistence;

use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\Contract\FormSummary;
use App\Forms\Application\Contract\FormView;
use App\Forms\Application\Contract\LeaderboardEntry;
use App\Forms\Application\Contract\PublishedForm;
use App\Forms\Application\Contract\ResponseView;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalFormReader implements FormReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function list(int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT f.id, f.title, f.kind, f.audience, f.identity_mode, f.status, f.version,
                    (SELECT count(*) FROM form_responses r WHERE r.form_id = f.id) AS responses
             FROM forms f ORDER BY f.created_at DESC, f.id LIMIT :limit OFFSET :offset',
            ['limit' => $limit, 'offset' => $offset],
        );

        return array_map(static fn (array $r): FormSummary => new FormSummary(
            (string) $r['id'],
            (string) $r['title'],
            (string) $r['kind'],
            (string) $r['audience'],
            (string) $r['identity_mode'],
            (string) $r['status'],
            (int) $r['responses'],
            (int) $r['version'],
        ), $rows);
    }

    public function find(string $id): ?FormView
    {
        $r = $this->connection->fetchAssociative(
            'SELECT f.*, (SELECT count(*) FROM form_responses x WHERE x.form_id = f.id) AS responses,
                    (SELECT min(x.expires_on) FROM form_responses x WHERE x.form_id = f.id) AS next_purge
             FROM forms f WHERE f.id = :id',
            ['id' => $id],
        );
        if (false === $r) {
            return null;
        }

        return new FormView(
            (string) $r['id'],
            (string) $r['title'],
            (string) $r['description'],
            (string) $r['kind'],
            (string) $r['audience'],
            (string) $r['identity_mode'],
            (string) $r['status'],
            self::time($r['opens_at']),
            self::time($r['closes_at']),
            null === $r['max_responses'] ? null : (int) $r['max_responses'],
            (int) $r['retention_months'],
            (bool) $r['leaderboard_enabled'],
            (int) $r['leaderboard_size'],
            (bool) $r['show_score'],
            (bool) $r['show_answers'],
            $this->groups((string) $r['id']),
            self::json($r['draft_definition']),
            (int) $r['current_version'],
            (int) $r['responses'],
            null === $r['next_purge'] ? null : new DateTimeImmutable((string) $r['next_purge']),
            (int) $r['version'],
        );
    }

    public function publishedByTokenHash(string $tokenHash, DateTimeImmutable $now): ?PublishedForm
    {
        return $this->published1('f.public_token_hash = :key', $tokenHash, $now);
    }

    public function published(string $id, DateTimeImmutable $now): ?PublishedForm
    {
        return $this->published1('f.id = :key', $id, $now);
    }

    public function internalPublished(DateTimeImmutable $now): array
    {
        $rows = $this->connection->fetchAllAssociative($this->publishedSql()." AND f.audience <> 'PUBLIC' ORDER BY f.created_at DESC, f.id", ['now' => Timestamps::toDatabase($now)]);

        return array_map(fn (array $r): PublishedForm => $this->publishedForm($r), $rows);
    }

    public function responses(string $formId, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, form_version, submitted_on, submitted_at, respondent_person_id, nickname, score, max_score, leaderboard_hidden, answers
             FROM form_responses WHERE form_id = :id ORDER BY submitted_on, id LIMIT :limit OFFSET :offset',
            ['id' => $formId, 'limit' => $limit, 'offset' => $offset],
        );

        return array_map(static fn (array $r): ResponseView => new ResponseView(
            (string) $r['id'],
            (int) $r['form_version'],
            new DateTimeImmutable((string) $r['submitted_on']),
            self::time($r['submitted_at']),
            null === $r['respondent_person_id'] ? null : (string) $r['respondent_person_id'],
            null === $r['nickname'] ? null : (string) $r['nickname'],
            null === $r['score'] ? null : (int) $r['score'],
            null === $r['max_score'] ? null : (int) $r['max_score'],
            (bool) $r['leaderboard_hidden'],
            self::json($r['answers']),
        ), $rows);
    }

    public function responseCount(string $formId): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM form_responses WHERE form_id = ?', [$formId]);
    }

    public function answerSets(string $formId): array
    {
        return array_map(static fn (mixed $json): array => self::json($json), $this->connection->fetchFirstColumn('SELECT answers FROM form_responses WHERE form_id = ? ORDER BY id', [$formId]));
    }

    public function leaderboard(string $formId, int $size): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT nickname, score, max_score FROM form_responses
             WHERE form_id = :id AND score IS NOT NULL AND NOT leaderboard_hidden
             ORDER BY score DESC, submitted_on, id LIMIT :size',
            ['id' => $formId, 'size' => $size],
        );
        $entries = [];
        foreach ($rows as $i => $r) {
            $entries[] = new LeaderboardEntry($i + 1, null === $r['nickname'] ? 'Anonymous' : (string) $r['nickname'], (int) $r['score'], (int) $r['max_score']);
        }

        return $entries;
    }

    public function hideFromLeaderboard(string $formId, string $responseId): bool
    {
        return 1 === $this->connection->executeStatement('UPDATE form_responses SET leaderboard_hidden = TRUE WHERE form_id = ? AND id = ?', [$formId, $responseId]);
    }

    private function published1(string $where, string $key, DateTimeImmutable $now): ?PublishedForm
    {
        $row = $this->connection->fetchAssociative(
            str_replace('WHERE f.status', 'WHERE '.$where.' AND f.status', $this->publishedSql()),
            ['now' => Timestamps::toDatabase($now), 'key' => $key],
        );

        return false === $row ? null : $this->publishedForm($row);
    }

    private function publishedSql(): string
    {
        return 'SELECT f.*, v.definition AS published_definition, (SELECT count(*) FROM form_responses x WHERE x.form_id = f.id) AS responses
                FROM forms f JOIN form_versions v ON v.form_id = f.id AND v.version = f.current_version
                WHERE f.status = \'PUBLISHED\' AND (f.opens_at IS NULL OR f.opens_at <= :now)';
    }

    /** @param array<string, mixed> $r */
    private function publishedForm(array $r): PublishedForm
    {
        return new PublishedForm(
            (string) $r['id'],
            (string) $r['title'],
            (string) $r['description'],
            (string) $r['kind'],
            (string) $r['audience'],
            (string) $r['identity_mode'],
            (int) $r['current_version'],
            self::json($r['published_definition']),
            $this->groups((string) $r['id']),
            self::time($r['opens_at']),
            self::time($r['closes_at']),
            null === $r['max_responses'] ? null : (int) $r['max_responses'],
            (int) $r['responses'],
            (int) $r['retention_months'],
            (bool) $r['leaderboard_enabled'],
        );
    }

    /** @return list<string> */
    private function groups(string $formId): array
    {
        return array_map('strval', $this->connection->fetchFirstColumn('SELECT community_id FROM form_audience_groups WHERE form_id = ? ORDER BY community_id', [$formId]));
    }

    private static function time(mixed $value): ?DateTimeImmutable
    {
        return null === $value ? null : Timestamps::fromDatabase((string) $value);
    }

    /** @return array<string, mixed> */
    private static function json(mixed $value): array
    {
        $decoded = json_decode((string) $value, true, 16);

        return \is_array($decoded) ? $decoded : [];
    }

    public function publishedDefinition(string $formId, int $version): ?array
    {
        $json = $this->connection->fetchOne('SELECT definition FROM form_versions WHERE form_id = ? AND version = ?', [$formId, $version]);

        return false === $json ? null : self::json($json);
    }
}

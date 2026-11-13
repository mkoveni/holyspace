<?php

declare(strict_types=1);

namespace App\Forms\Infrastructure\Persistence;

use App\Forms\Domain\FormResponse;
use App\Forms\Domain\Repository\ResponseRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalResponseStore implements ResponseRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function count(string $formId): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM form_responses WHERE form_id = ?', [$formId]);
    }

    public function hasSubmission(string $formId, string $submissionKey): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM form_responses WHERE form_id = ? AND submission_key = ?', [$formId, $submissionKey]);
    }

    public function hasAccountResponse(string $formId, string $accountId): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM form_responses WHERE form_id = ? AND respondent_account_id = ?', [$formId, $accountId]);
    }

    public function insert(FormResponse $r): void
    {
        $this->connection->executeStatement(
            'INSERT INTO form_responses (id, form_id, form_version, identity_mode, submission_key, answers, score, max_score, nickname,
                respondent_person_id, respondent_account_id, submitted_on, submitted_at, expires_on)
             VALUES (:id, :form, :version, :identity, :key, :answers, :score, :max, :nickname, :person, :account, :on, :at, :expires)',
            [
                'id' => $r->id,
                'form' => $r->formId,
                'version' => $r->formVersion,
                'identity' => $r->identity->value,
                'key' => $r->submissionKey,
                'answers' => json_encode($r->answers, \JSON_THROW_ON_ERROR),
                'score' => $r->score,
                'max' => $r->maxScore,
                'nickname' => $r->nickname,
                'person' => $r->personId,
                'account' => $r->accountId,
                'on' => $r->submittedOn->format('Y-m-d'),
                'at' => null === $r->submittedAt ? null : Timestamps::toDatabase($r->submittedAt),
                'expires' => $r->expiresOn->format('Y-m-d'),
            ],
            ['version' => ParameterType::INTEGER, 'score' => ParameterType::INTEGER, 'max' => ParameterType::INTEGER],
        );
    }

    public function insertMark(string $formId, string $markHash): bool
    {
        return 1 === $this->connection->executeStatement(
            'INSERT INTO form_participation_marks (form_id, mark_hash) VALUES (?, ?) ON CONFLICT DO NOTHING',
            [$formId, $markHash],
        );
    }

    public function deleteExpired(DateTimeImmutable $today): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'WITH gone AS (DELETE FROM form_responses WHERE expires_on <= :today RETURNING form_id) SELECT form_id, count(*) AS n FROM gone GROUP BY form_id',
            ['today' => $today->format('Y-m-d')],
        );
        $this->connection->executeStatement('DELETE FROM form_participation_marks m WHERE NOT EXISTS (SELECT 1 FROM form_responses r WHERE r.form_id = m.form_id)');

        $deleted = [];
        foreach ($rows as $row) {
            $deleted[(string) $row['form_id']] = (int) $row['n'];
        }

        return $deleted;
    }
}

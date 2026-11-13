<?php

declare(strict_types=1);

namespace App\Forms\Infrastructure\Persistence;

use App\Forms\Domain\Form;
use App\Forms\Domain\FormDefinition;
use App\Forms\Domain\FormException;
use App\Forms\Domain\FormKind;
use App\Forms\Domain\FormSettings;
use App\Forms\Domain\FormStatus;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\Revision;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use JsonException;

final readonly class DbalFormStore implements FormRepository
{
    public function __construct(private Connection $connection, private IdGenerator $ids)
    {
    }

    public function insert(Form $form): void
    {
        $this->connection->executeStatement(
            'INSERT INTO forms (id, title, description, kind, audience, identity_mode, status, public_token_hash, opens_at, closes_at, max_responses, retention_months,
                leaderboard_enabled, leaderboard_size, show_score, show_answers, current_version, draft_definition, owner_account_id, created_at, updated_at, version)
             VALUES (:id, :title, :description, :kind, :audience, :identity, :status, :token, :opens, :closes, :max, :retention, :board, :size, :score, :answers, :current, :draft, :owner, :created, :updated, :version)',
            $this->params($form) + ['owner' => $form->ownerAccountId, 'created' => Timestamps::toDatabase($form->revision->timeline->createdAt)],
            ['board' => ParameterType::BOOLEAN, 'score' => ParameterType::BOOLEAN, 'answers' => ParameterType::BOOLEAN],
        );
        $this->replaceGroups($form);
    }

    public function save(Form $form): void
    {
        $params = $this->params($form);
        $params['previous'] = $form->revision->previousVersion();
        try {
            $changed = $this->connection->executeStatement(
                'UPDATE forms SET title = :title, description = :description, kind = :kind, audience = :audience, identity_mode = :identity, status = :status,
                    public_token_hash = :token, opens_at = :opens, closes_at = :closes, max_responses = :max, retention_months = :retention,
                    leaderboard_enabled = :board, leaderboard_size = :size, show_score = :score, show_answers = :answers, current_version = :current,
                    draft_definition = :draft, updated_at = :updated, version = :version
                 WHERE id = :id AND version = :previous',
                $params,
                ['board' => ParameterType::BOOLEAN, 'score' => ParameterType::BOOLEAN, 'answers' => ParameterType::BOOLEAN],
            );
        } catch (DriverException $e) {
            // SQLSTATE 23514: the database guard refuses a changed promise once responses exist.
            if ('23514' !== $e->getSQLState()) {
                throw $e;
            }
            throw FormException::conflict('This form already has responses, so its audience, kind and identity mode cannot change.');
        }
        if (1 !== $changed) {
            throw FormException::conflict('The form changed. Refresh before trying again.');
        }
        $this->replaceGroups($form);
    }

    public function publishVersion(Form $form, DateTimeImmutable $now): void
    {
        $this->connection->executeStatement(
            'INSERT INTO form_versions (form_id, version, definition, published_at) VALUES (:id, :version, :definition, :at)',
            ['id' => $form->id, 'version' => $form->currentVersion, 'definition' => $this->json($form->settings->definition), 'at' => Timestamps::toDatabase($now)],
        );
    }

    public function findForUpdate(string $id): ?Form
    {
        return $this->load('f.id = :key', $id);
    }

    public function findByTokenHashForUpdate(string $tokenHash): ?Form
    {
        return $this->load('f.public_token_hash = :key', $tokenHash);
    }

    public function definition(string $formId, int $version): ?FormDefinition
    {
        $row = $this->connection->fetchAssociative(
            'SELECT v.definition, f.kind FROM form_versions v JOIN forms f ON f.id = v.form_id WHERE v.form_id = :id AND v.version = :version',
            ['id' => $formId, 'version' => $version],
        );
        if (false === $row) {
            return null;
        }

        return FormDefinition::fromArray($this->decode((string) $row['definition']), FormKind::Quiz->value === $row['kind']);
    }

    public function audit(string $formId, string $action, string $detail, ?string $actorId, DateTimeImmutable $at): void
    {
        $this->connection->insert('forms_audit', [
            'id' => $this->ids->uuid(),
            'form_id' => $formId,
            'action' => $action,
            'detail' => $detail,
            'actor_account_id' => $actorId,
            'created_at' => Timestamps::toDatabase($at),
        ]);
    }

    private function load(string $where, string $key): ?Form
    {
        $row = $this->connection->fetchAssociative('SELECT f.* FROM forms f WHERE '.$where.' FOR UPDATE', ['key' => $key]);
        if (false === $row) {
            return null;
        }
        $groups = array_map('strval', $this->connection->fetchFirstColumn('SELECT community_id FROM form_audience_groups WHERE form_id = ? ORDER BY community_id', [$row['id']]));
        $settings = FormSettings::of(
            (string) $row['title'],
            (string) $row['description'],
            (string) $row['kind'],
            (string) $row['audience'],
            (string) $row['identity_mode'],
            null === $row['opens_at'] ? null : Timestamps::fromDatabase((string) $row['opens_at']),
            null === $row['closes_at'] ? null : Timestamps::fromDatabase((string) $row['closes_at']),
            null === $row['max_responses'] ? null : (int) $row['max_responses'],
            (int) $row['retention_months'],
            (bool) $row['leaderboard_enabled'],
            (int) $row['leaderboard_size'],
            (bool) $row['show_score'],
            (bool) $row['show_answers'],
            $groups,
            $this->decode((string) $row['draft_definition']),
        );

        return Form::reconstitute(
            (string) $row['id'],
            (string) $row['owner_account_id'],
            $settings,
            FormStatus::from((string) $row['status']),
            null === $row['public_token_hash'] ? null : (string) $row['public_token_hash'],
            (int) $row['current_version'],
            Revision::reconstitute((int) $row['version'], Timestamps::fromDatabase((string) $row['created_at']), Timestamps::fromDatabase((string) $row['updated_at'])),
        );
    }

    /** @return array<string, mixed> */
    private function params(Form $form): array
    {
        $s = $form->settings;

        return [
            'id' => $form->id,
            'title' => $s->title,
            'description' => $s->description,
            'kind' => $s->kind->value,
            'audience' => $s->audience->value,
            'identity' => $s->identity->value,
            'status' => $form->status->value,
            'token' => $form->tokenHash,
            'opens' => null === $s->opensAt ? null : Timestamps::toDatabase($s->opensAt),
            'closes' => null === $s->closesAt ? null : Timestamps::toDatabase($s->closesAt),
            'max' => $s->maxResponses,
            'retention' => $s->retentionMonths,
            'board' => $s->leaderboardEnabled,
            'size' => $s->leaderboardSize,
            'score' => $s->showScore,
            'answers' => $s->showAnswers,
            'current' => $form->currentVersion,
            'draft' => $this->json($s->definition),
            'updated' => Timestamps::toDatabase($form->revision->timeline->updatedAt),
            'version' => $form->revision->version,
        ];
    }

    private function replaceGroups(Form $form): void
    {
        $this->connection->executeStatement('DELETE FROM form_audience_groups WHERE form_id = ?', [$form->id]);
        foreach ($form->settings->groupIds as $communityId) {
            $this->connection->insert('form_audience_groups', ['form_id' => $form->id, 'community_id' => $communityId]);
        }
    }

    /** @param array<mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR);
    }

    /** @return array<mixed> */
    private function decode(string $json): array
    {
        try {
            $value = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw FormException::invalid('A stored form definition is unreadable.');
        }

        return \is_array($value) ? $value : [];
    }
}

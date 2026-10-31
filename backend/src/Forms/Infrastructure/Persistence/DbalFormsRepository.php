<?php

declare(strict_types=1);

namespace App\Forms\Infrastructure\Persistence;

use App\Forms\Domain\Model\ChurchForm;
use App\Forms\Domain\Model\FormSubmission;
use App\Forms\Domain\Repository\FormsRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(FormsRepository::class)]
final class DbalFormsRepository extends WorkflowDocumentRepository implements FormsRepository
{
    public function __construct(Connection $db, private readonly FormsMapper $formMapper, WorkflowAuditJournal $audit)
    {
        parent::__construct($db, $formMapper, 'forms_records', $audit);
    }

    public function save(WorkflowRecord $record): void
    {
        $this->db->transactional(function () use ($record) {
            parent::save($record);
            $this->db->update('forms_records', ['form_id' => $record instanceof ChurchForm ? $record->id : $record->formId, 'public_token' => $record instanceof ChurchForm ? $record->publicToken : null, 'submission_key' => $record instanceof FormSubmission ? $record->submissionKey : null], ['id' => $record->id]);
        });
    }

    public function publicForm(string $token): ?ChurchForm
    {
        $payload = $this->db->fetchOne("SELECT payload FROM forms_records WHERE public_token = ? AND kind = 'forms'", [$token]);

        return false === $payload ? null : $this->formMapper->hydrate('forms', $payload);
    }

    public function bySubmissionKey(string $formId, string $key): ?WorkflowRecord
    {
        $payload = $this->db->fetchOne("SELECT payload FROM forms_records WHERE form_id = ? AND submission_key = ? AND kind = 'submissions'", [$formId, $key]);

        return false === $payload ? null : $this->formMapper->hydrate('submissions', $payload);
    }

    public function page(string $kind, ?string $formId, ?string $after, int $limit): array
    {
        $query = $this->db->createQueryBuilder()->select('payload')->from('forms_records')->where('kind = :kind')->setParameter('kind', $kind)->orderBy('id')->setMaxResults($limit);
        if (null !== $formId) {
            $query->andWhere('form_id = :form')->setParameter('form', $formId);
        }
        if (null !== $after) {
            $query->andWhere('id > :after')->setParameter('after', $after);
        }

        return array_map(fn ($row) => $this->formMapper->hydrate($kind, $row['payload']), $query->executeQuery()->fetchAllAssociative());
    }
}

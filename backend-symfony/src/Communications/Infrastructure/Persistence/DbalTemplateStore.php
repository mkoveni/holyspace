<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Persistence;

use App\Communications\Application\Contract\TemplatePage;
use App\Communications\Application\Contract\TemplateReader;
use App\Communications\Application\Contract\TemplateRevision;
use App\Communications\Application\Contract\TemplateView;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\EmailTemplate;
use App\Communications\Domain\Repository\TemplateRepository;
use App\Communications\Domain\TemplateContent;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DbalTemplateStore implements TemplateReader, TemplateRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $id): ?TemplateView
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM communication_templates WHERE id = :id', ['id' => $id]);

        return false === $row ? null : self::view($row);
    }

    public function list(string $search, int $limit, int $offset): TemplatePage
    {
        $where = 'TRUE';
        $params = [];
        if ('' !== trim($search)) {
            $where = "(name ILIKE :pattern ESCAPE '!' OR template_key ILIKE :pattern ESCAPE '!')";
            $params['pattern'] = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        }
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM communication_templates WHERE $where ORDER BY updated_at DESC, id LIMIT :limit OFFSET :offset", $params + ['limit' => $limit, 'offset' => $offset]);
        $total = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM communication_templates WHERE $where", $params);

        return new TemplatePage(array_map(self::view(...), $rows), $total);
    }

    public function revisions(string $id, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM communication_template_revisions WHERE template_id = :id ORDER BY version DESC LIMIT :limit OFFSET :offset', ['id' => $id, 'limit' => $limit, 'offset' => $offset]);

        return array_map(self::revision(...), $rows);
    }

    public function published(string $key): ?TemplateRevision
    {
        $row = $this->connection->fetchAssociative(
            "SELECT r.* FROM communication_template_revisions r JOIN communication_templates t ON t.id = r.template_id WHERE t.template_key = :key AND t.status <> 'ARCHIVED' AND r.version = t.published_version",
            ['key' => $key],
        );

        return false === $row ? null : self::revision($row);
    }

    public function lock(string $id): ?EmailTemplate
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM communication_templates WHERE id = :id FOR UPDATE', ['id' => $id]);

        return false === $row ? null : EmailTemplate::reconstitute(
            (string) $row['id'],
            (string) $row['template_key'],
            (string) $row['name'],
            new TemplateContent((string) $row['subject'], (string) $row['html'], self::variables((string) $row['variables'])),
            (string) $row['status'],
            (int) $row['version'],
            null === $row['updated_by'] ? null : (string) $row['updated_by'],
            Timestamps::fromDatabase((string) $row['updated_at']),
            null === $row['published_version'] ? null : (int) $row['published_version'],
        );
    }

    public function insert(EmailTemplate $t): void
    {
        try {
            $this->connection->insert('communication_templates', ['id' => $t->id, 'template_key' => $t->key, 'name' => $t->name, 'subject' => $t->content->subject, 'html' => $t->content->html, 'variables' => implode(',', $t->content->variables), 'status' => $t->status, 'version' => $t->version, 'updated_by' => $t->updatedBy, 'updated_at' => Timestamps::toDatabase($t->updatedAt)]);
        } catch (UniqueConstraintViolationException) {
            throw CommunicationException::conflict('A template with this key already exists.');
        }
    }

    public function save(EmailTemplate $t): void
    {
        $this->connection->update('communication_templates', ['name' => $t->name, 'subject' => $t->content->subject, 'html' => $t->content->html, 'variables' => implode(',', $t->content->variables), 'status' => $t->status, 'version' => $t->version, 'updated_by' => $t->updatedBy, 'updated_at' => Timestamps::toDatabase($t->updatedAt), 'published_version' => $t->publishedVersion], ['id' => $t->id]);
    }

    public function publish(EmailTemplate $t): void
    {
        $this->connection->insert('communication_template_revisions', ['template_id' => $t->id, 'version' => $t->version, 'subject' => $t->content->subject, 'html' => $t->content->html, 'variables' => implode(',', $t->content->variables), 'published_by' => $t->updatedBy, 'published_at' => Timestamps::toDatabase($t->updatedAt)]);
    }

    /** @return list<string> */
    private static function variables(string $value): array
    {
        return '' === $value ? [] : explode(',', $value);
    }

    /** @param array<string, mixed> $r */
    private static function view(array $r): TemplateView
    {
        return new TemplateView((string) $r['id'], (string) $r['template_key'], (string) $r['name'], (string) $r['subject'], (string) $r['html'], self::variables((string) $r['variables']), (string) $r['status'], (int) $r['version'], null === $r['published_version'] ? null : (int) $r['published_version'], Timestamps::fromDatabase((string) $r['updated_at']));
    }

    /** @param array<string, mixed> $r */
    private static function revision(array $r): TemplateRevision
    {
        return new TemplateRevision((int) $r['version'], (string) $r['subject'], (string) $r['html'], self::variables((string) $r['variables']), Timestamps::fromDatabase((string) $r['published_at']));
    }
}

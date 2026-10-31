<?php

declare(strict_types=1);

namespace App\Media\Infrastructure;

use App\Media\Application\ReadModel\ImageReader;
use App\Media\Domain\ImageStorage;
use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use DomainException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;

#[AsAlias(ImageStorage::class)]
#[AsAlias(ImageReader::class)]
final readonly class DbalImageStorage implements ImageStorage, ImageReader
{
    public function __construct(private Connection $db, private WorkflowAuditJournal $audit)
    {
    }

    public function get(string $target, string $id, bool $content = false): array
    {
        $columns = 'version, mime, width, height, byte_size, updated_at'.($content ? ', content' : '');
        $row = $this->db->fetchAssociative('SELECT '.$columns.' FROM media_images WHERE target = ? AND owner_id = ?', [$target, $id]);
        if (!$row) {
            return ['version' => 0, 'image' => null];
        }
        $image = null === $row['mime'] ? null : ['mime' => $row['mime'], 'width' => (int) $row['width'], 'height' => (int) $row['height'], 'bytes' => (int) $row['byte_size'], 'updatedAt' => $row['updated_at']];
        if ($content && $image) {
            $image['content'] = is_resource($row['content']) ? stream_get_contents($row['content']) : $row['content'];
        }

        return ['version' => (int) $row['version'], 'image' => $image];
    }

    public function save(string $target, string $id, ?array $image, int $expectedVersion): array
    {
        $current = $this->get($target, $id);
        if ($current['version'] !== $expectedVersion) {
            throw new DomainException('This image changed. Reload before trying again.');
        }
        $data = ['version' => $expectedVersion + 1, 'mime' => $image['mime'] ?? null, 'width' => $image['width'] ?? null, 'height' => $image['height'] ?? null, 'byte_size' => isset($image['content']) ? strlen($image['content']) : null, 'content' => $image['content'] ?? null, 'updated_at' => gmdate(DATE_ATOM)];
        if (0 === $expectedVersion) {
            $this->db->insert('media_images', ['target' => $target, 'owner_id' => $id] + $data, ['content' => ParameterType::BINARY]);
        } elseif (1 !== $this->db->update('media_images', $data, ['target' => $target, 'owner_id' => $id, 'version' => $expectedVersion], ['content' => ParameterType::BINARY])) {
            throw new DomainException('This image changed. Reload before trying again.');
        }
        $context = ['person' => 'people', 'family' => 'families', 'event' => 'events', 'form' => 'forms'][$target];
        $auditId = Uuid::v5(Uuid::fromString($id), 'portal-image:'.$target)->toRfc4122();
        $record = new class(id: $auditId, version: $expectedVersion) extends WorkflowRecord {
        };
        $this->audit->append($context, 'image', $record);

        return $this->get($target, $id);
    }
}

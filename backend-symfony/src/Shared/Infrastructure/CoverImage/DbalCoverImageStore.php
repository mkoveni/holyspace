<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\CoverImage;

use App\Shared\Application\CoverImage\CoverImageContent;
use App\Shared\Application\CoverImage\CoverImageInfo;
use App\Shared\Application\CoverImage\CoverImageStore;
use App\Shared\Domain\CoverImage;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalCoverImageStore implements CoverImageStore
{
    public function __construct(private Connection $connection)
    {
    }

    public function add(string $id, CoverImage $image, DateTimeImmutable $at): void
    {
        $this->connection->insert('cover_images', [
            'id' => $id,
            'content_type' => $image->contentType,
            'width' => $image->width,
            'height' => $image->height,
            'byte_size' => $image->byteSize(),
            'content' => $image->content,
            'created_at' => Timestamps::toDatabase($at),
        ], ['content' => ParameterType::LARGE_OBJECT]);
    }

    public function remove(string $id): void
    {
        $this->connection->delete('cover_images', ['id' => $id]);
    }

    public function info(string $id): ?CoverImageInfo
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'content_type', 'width', 'height', 'byte_size', 'created_at')
            ->from('cover_images')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : new CoverImageInfo(
            (string) $row['id'],
            (string) $row['content_type'],
            (int) $row['width'],
            (int) $row['height'],
            (int) $row['byte_size'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    public function content(string $id): ?CoverImageContent
    {
        $row = $this->connection->createQueryBuilder()
            ->select('content_type', 'content')
            ->from('cover_images')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $content = $row['content'];

        return new CoverImageContent((string) $row['content_type'], \is_resource($content) ? (string) stream_get_contents($content) : (string) $content);
    }
}

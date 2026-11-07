<?php

declare(strict_types=1);

namespace App\Communications\Domain;

use DateTimeImmutable;

/** A named email template. Editing makes a draft; publishing freezes an immutable revision. */
final readonly class EmailTemplate
{
    public const string DRAFT = 'DRAFT';
    public const string PUBLISHED = 'PUBLISHED';
    public const string ARCHIVED = 'ARCHIVED';

    public string $name;

    private function __construct(
        public string $id,
        public string $key,
        string $name,
        public TemplateContent $content,
        public string $status,
        public int $version,
        public ?string $updatedBy,
        public DateTimeImmutable $updatedAt,
        public ?int $publishedVersion,
    ) {
        if (1 !== preg_match('/\A[a-z][a-z0-9.-]{2,99}\z/', $key)) {
            throw CommunicationException::invalid('Use a template key of 3–100 lowercase letters, numbers, dots or hyphens.');
        }
        $this->name = CommunicationRules::text($name, 150);
    }

    public static function draft(string $id, string $key, string $name, TemplateContent $content, string $actor, DateTimeImmutable $at): self
    {
        return new self($id, $key, $name, $content, self::DRAFT, 0, $actor, $at, null);
    }

    public static function reconstitute(string $id, string $key, string $name, TemplateContent $content, string $status, int $version, ?string $updatedBy, DateTimeImmutable $updatedAt, ?int $publishedVersion): self
    {
        return new self($id, $key, $name, $content, $status, $version, $updatedBy, $updatedAt, $publishedVersion);
    }

    public function edit(string $name, TemplateContent $content, string $actor, DateTimeImmutable $at): self
    {
        if (self::ARCHIVED === $this->status) {
            throw CommunicationException::conflict('Archived templates cannot be edited.');
        }

        return new self($this->id, $this->key, $name, $content, self::DRAFT, $this->version + 1, $actor, $at, $this->publishedVersion);
    }

    public function publish(string $actor, DateTimeImmutable $at): self
    {
        if (self::ARCHIVED === $this->status) {
            throw CommunicationException::conflict('Archived templates cannot be published.');
        }
        if (self::PUBLISHED === $this->status) {
            throw CommunicationException::conflict('This revision is already published.');
        }

        return new self($this->id, $this->key, $this->name, $this->content, self::PUBLISHED, $this->version + 1, $actor, $at, $this->version + 1);
    }

    public function archive(string $actor, DateTimeImmutable $at): self
    {
        return new self($this->id, $this->key, $this->name, $this->content, self::ARCHIVED, $this->version + 1, $actor, $at, $this->publishedVersion);
    }
}

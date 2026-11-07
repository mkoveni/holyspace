<?php

declare(strict_types=1);

namespace App\Communities\Domain;

final readonly class Community
{
    private function __construct(
        public string $id,
        public string $kind,
        public string $name,
        public string $description,
        public ?string $parentId,
        public string $campus,
        public bool $active,
        public int $version,
    ) {
        if (!in_array($kind, ['MINISTRY', 'GROUP'], true) || ($parentId !== null && $kind !== 'GROUP')) {
            throw CommunityException::invalid('Choose a ministry or a group with an optional parent ministry.');
        }
    }

    public static function reconstitute(
        string $id,
        string $kind,
        string $name,
        string $description,
        ?string $parentId,
        string $campus,
        bool $active,
        int $version,
    ): self {
        return new self(
            $id,
            $kind,
            CommunityRules::text($name, 150),
            CommunityRules::optional($description, 5000),
            $parentId,
            CommunityRules::optional($campus, 150),
            $active,
            $version
        );
    }

    public function update(string $name, string $description, ?string $parentId, string $campus, bool $active): self
    {
        return self::reconstitute(
            $this->id,
            $this->kind,
            $name,
            $description,
            $parentId,
            $campus,
            $active,
            $this->version + 1
        );
    }
}

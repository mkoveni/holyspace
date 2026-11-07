<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

interface TemplateReader
{
    public function find(string $id): ?TemplateView;

    public function list(string $search, int $limit, int $offset): TemplatePage;

    /** @return list<TemplateRevision> newest first */
    public function revisions(string $id, int $limit, int $offset): array;

    /** The revision currently published for the key, unless the template is archived. */
    public function published(string $key): ?TemplateRevision;
}

<?php

declare(strict_types=1);

namespace App\Media\Application\Contract;

/** What other contexts may ask Media: which stored file is current, in one query for a whole page of owners. */
interface MediaImages
{
    /**
     * @param string       $kind     an `ImageKind` value, such as `event`
     * @param list<string> $ownerIds
     *
     * @return array<string, string> owner id => current file id, only for owners that have a picture
     */
    public function fileIds(string $kind, array $ownerIds): array;
}

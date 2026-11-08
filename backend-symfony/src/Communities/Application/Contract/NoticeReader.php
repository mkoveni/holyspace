<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

interface NoticeReader
{
    public function notice(string $id): ?NoticeView;

    /** @return list<string> notice (communication) ids of the community, newest first */
    public function noticeIds(string $communityId, int $limit, int $offset): array;
}

<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Communities\Application\Contract\NoticeReader;
use App\Communities\Application\Contract\NoticeView;
use App\Communities\Domain\CommunityNotice;
use App\Communities\Domain\Repository\NoticeRepository;

final class MemoryNotices implements NoticeReader, NoticeRepository
{
    /** @var array<string, CommunityNotice> */
    public array $notices = [];

    public function notice(string $id): ?NoticeView
    {
        $n = $this->notices[$id] ?? null;

        return null === $n ? null : new NoticeView($n->id, $n->communityId, $n->membershipId, $n->contactId, $n->requestHash, $n->reviewHash, $n->createdBy, $n->deliveryId);
    }

    public function noticeIds(string $communityId, int $limit, int $offset): array
    {
        return array_keys($this->notices);
    }

    public function insert(CommunityNotice $notice): void
    {
        $this->notices[$notice->id] = $notice;
    }
}

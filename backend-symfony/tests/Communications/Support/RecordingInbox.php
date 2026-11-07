<?php

declare(strict_types=1);

namespace App\Tests\Communications\Support;

use App\Communications\Application\Contract\InboxReader;
use App\Communications\Application\Contract\InboxWriter;
use App\Communications\Application\Contract\MemberInbox;
use App\Communications\Application\Contract\MemberNotification;
use DateTimeImmutable;

/** In-memory inbox that records which account each call was scoped to. */
final class RecordingInbox implements InboxReader, InboxWriter
{
    /** @var list<string> */
    public array $calls = [];

    public function list(string $account, bool $unreadOnly, int $limit, int $offset): MemberInbox
    {
        $this->calls[] = "list:$account";

        return new MemberInbox([], 0, false);
    }

    public function preferences(string $account): array
    {
        $this->calls[] = "preferences:$account";

        return [];
    }

    public function notification(string $account, string $id): MemberNotification
    {
        $this->calls[] = "notification:$account";

        return new MemberNotification($id, 'EVENTS', 'Title', 'Message', 'EVENTS', new DateTimeImmutable(), null);
    }

    public function read(string $account, string $notification, bool $read, DateTimeImmutable $at): void
    {
        $this->calls[] = "read:$account";
    }

    public function preference(string $account, string $topic, bool $enabled, int $expectedVersion): void
    {
        $this->calls[] = "preference:$account";
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

use App\ChildrenMinistry\Safeguarding\Application\SupportRecordView;
use App\Shared\Domain\CalendarDate;

/** Read side of confidential support records. Only Safeguarding's audited use cases may call it. */
interface SupportReader
{
    public function supportRecord(string $childId): ?SupportRecordView;

    /** @return list<string> child ids whose retention ended before the date */
    public function expiredSupport(CalendarDate $date, int $limit): array;
}

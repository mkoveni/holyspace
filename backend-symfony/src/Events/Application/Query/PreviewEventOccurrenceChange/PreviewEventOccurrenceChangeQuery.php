<?php

declare(strict_types=1);

namespace App\Events\Application\Query\PreviewEventOccurrenceChange;

use App\Events\Application\OccurrenceChangeDraft;

final readonly class PreviewEventOccurrenceChangeQuery
{
    public function __construct(public OccurrenceChangeDraft $draft)
    {
    }
}

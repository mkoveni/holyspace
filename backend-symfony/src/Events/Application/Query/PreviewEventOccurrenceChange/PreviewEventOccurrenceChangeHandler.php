<?php

declare(strict_types=1);

namespace App\Events\Application\Query\PreviewEventOccurrenceChange;

use App\Events\Application\Contract\ChangePreviewItem;
use App\Events\Application\Contract\OccurrenceChangePreview;
use App\Events\Application\Contract\OccurrenceView;
use App\Events\Application\OccurrenceChange;
use App\Events\Application\OccurrenceChangeReview;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class PreviewEventOccurrenceChangeHandler
{
    public function __construct(private PermissionChecker $authorization, private OccurrenceChangeReview $review)
    {
    }

    public function handle(PreviewEventOccurrenceChangeQuery $query): OccurrenceChangePreview
    {
        $this->authorization->requirePermission('events.read');
        $plan = $this->review->plan($query->draft);

        return new OccurrenceChangePreview(
            $plan->hash,
            array_map(static fn (OccurrenceChange $c): ChangePreviewItem => new ChangePreviewItem(OccurrenceView::of($c->before), OccurrenceView::of($c->after), $c->registrations, $c->registrationVersion, $c->attended), $plan->changes),
            $plan->skipped,
            $plan->confirmedAffected,
            $plan->waitlistedAffected,
            $plan->attendedAffected,
        );
    }
}

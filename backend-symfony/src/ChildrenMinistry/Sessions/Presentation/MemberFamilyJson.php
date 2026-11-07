<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Safeguarding\Application\Contract\ReadinessStepStatus;
use App\ChildrenMinistry\Sessions\Application\FamilyArrivalReview;
use App\ChildrenMinistry\Sessions\Application\FamilyChild;
use App\ChildrenMinistry\Sessions\Application\FamilyPage;
use App\ChildrenMinistry\Sessions\Application\FamilyProgramme;
use App\ChildrenMinistry\Sessions\Application\FamilySession;
use App\ChildrenMinistry\Sessions\Application\FamilyVisit;
use App\Shared\Presentation\Json;

/** Wire format of the family views, matching the Java API (and the mobile models). No evidence, collectors or staff data. */
final class MemberFamilyJson
{
    private function __construct()
    {
    }

    /**
     * @template T
     *
     * @param FamilyPage<T>                     $page
     * @param callable(T): array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    public static function page(FamilyPage $page, callable $item): array
    {
        return ['verified' => $page->verified, 'items' => array_map($item, $page->items), 'hasMore' => $page->hasMore];
    }

    /** @return array<string, mixed> */
    public static function child(FamilyChild $child): array
    {
        return ['id' => $child->id, 'name' => $child->name];
    }

    /** @return array<string, mixed> */
    public static function programme(FamilyProgramme $programme): array
    {
        return [
            'id' => $programme->id,
            'name' => $programme->name,
            'validFrom' => $programme->validFrom->iso(),
            'validUntil' => $programme->validUntil->iso(),
            'className' => $programme->className,
            'readiness' => array_map(static fn (ReadinessStepStatus $step): array => ['code' => $step->code, 'complete' => $step->complete], $programme->readiness),
            'timeZone' => $programme->timeZone,
        ];
    }

    /** @return array<string, mixed> */
    public static function session(FamilySession $session): array
    {
        return [
            'id' => $session->id,
            'programme' => $session->programme,
            'className' => $session->className,
            'room' => $session->room,
            'location' => $session->location,
            'timeZone' => $session->timeZone,
            'startsAt' => Json::instant($session->startsAt),
            'endsAt' => Json::instant($session->endsAt),
            'status' => $session->status,
            'issues' => $session->issues,
            'arrivalWindowOpen' => $session->arrivalWindowOpen,
        ];
    }

    /** @return array<string, mixed> */
    public static function visit(FamilyVisit $visit): array
    {
        $v = $visit->visit;

        return [
            'visit' => [
                'id' => $v->id,
                'sessionId' => $v->sessionId,
                'programme' => $v->programme,
                'className' => $v->className,
                'room' => $v->room,
                'location' => $v->location,
                'timeZone' => $v->timeZone,
                'arrivedAt' => Json::instant($v->arrivedAt),
                'collectedAt' => null === $v->collectedAt ? null : Json::instant($v->collectedAt),
                'version' => $v->version,
                'labelIssued' => $v->labelIssued,
            ],
            'canRequestPickupLabel' => $visit->canRequestPickupLabel,
        ];
    }

    /** @return array<string, mixed> */
    public static function arrivalReview(FamilyArrivalReview $review): array
    {
        return ['child' => self::child($review->child), 'issues' => $review->issues];
    }
}

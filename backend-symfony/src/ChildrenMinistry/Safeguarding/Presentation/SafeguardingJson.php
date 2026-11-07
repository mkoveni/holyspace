<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation;

use App\ChildrenMinistry\Safeguarding\Application\ConsentPolicyView;
use App\ChildrenMinistry\Safeguarding\Application\ConsentView;
use App\ChildrenMinistry\Safeguarding\Application\GuardianAuthorityView;
use App\ChildrenMinistry\Safeguarding\Application\PickupAuthorizationView;
use App\ChildrenMinistry\Safeguarding\Application\SafeguardingView;
use App\Shared\Presentation\Json;

/** Wire format of safeguarding resources, matching the Java API's JSON field names. */
final class SafeguardingJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function safeguarding(SafeguardingView $view): array
    {
        return [
            'authorities' => array_map(self::authority(...), $view->authorities),
            'consents' => array_map(self::consent(...), $view->consents),
            'pickupAuthorizations' => array_map(self::pickup(...), $view->pickupAuthorizations),
        ];
    }

    /** @return array<string, mixed> */
    public static function policy(ConsentPolicyView $policy): array
    {
        return [
            'id' => $policy->id,
            'programmeId' => $policy->programmeId,
            'label' => $policy->label,
            'documentReference' => $policy->documentReference,
            'contentHash' => $policy->contentHash,
            'createdAt' => Json::instant($policy->createdAt),
        ];
    }

    /** @return array<string, mixed> */
    private static function consent(ConsentView $consent): array
    {
        return [
            'id' => $consent->id,
            'programmeId' => $consent->programmeId,
            'policyId' => $consent->policyId,
            'childId' => $consent->childId,
            'authorityId' => $consent->authorityId,
            'validFrom' => Json::instant($consent->validFrom),
            'validUntil' => Json::instant($consent->validUntil),
            'acceptedAt' => Json::instant($consent->acceptedAt),
            'recordedBy' => $consent->recordedBy,
            'evidenceReference' => $consent->evidenceReference,
            'revokedAt' => null === $consent->revokedAt ? null : Json::instant($consent->revokedAt),
            'revocationReason' => $consent->revocationReason,
            'version' => $consent->version,
        ];
    }

    /** @return array<string, mixed> */
    private static function authority(GuardianAuthorityView $authority): array
    {
        return [
            'id' => $authority->id,
            'childId' => $authority->childId,
            'guardianId' => $authority->guardianId,
            'validFrom' => Json::instant($authority->validFrom),
            'validUntil' => Json::instant($authority->validUntil),
            'evidenceReference' => $authority->evidenceReference,
            'verifiedBy' => $authority->verifiedBy,
            'revokedAt' => null === $authority->revokedAt ? null : Json::instant($authority->revokedAt),
            'revocationReason' => $authority->revocationReason,
            'version' => $authority->version,
        ];
    }

    /** @return array<string, mixed> */
    private static function pickup(PickupAuthorizationView $grant): array
    {
        return [
            'id' => $grant->id,
            'programmeId' => $grant->programmeId,
            'childId' => $grant->childId,
            'collectorId' => $grant->collectorId,
            'authorityId' => $grant->authorityId,
            'validFrom' => Json::instant($grant->validFrom),
            'validUntil' => Json::instant($grant->validUntil),
            'evidenceReference' => $grant->evidenceReference,
            'recordedBy' => $grant->recordedBy,
            'revokedAt' => null === $grant->revokedAt ? null : Json::instant($grant->revokedAt),
            'revocationReason' => $grant->revocationReason,
            'version' => $grant->version,
        ];
    }
}

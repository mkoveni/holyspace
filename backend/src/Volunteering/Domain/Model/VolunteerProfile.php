<?php

declare(strict_types=1);

namespace App\Volunteering\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class VolunteerProfile extends WorkflowRecord
{
    public function __construct(
        public readonly string $personId, public readonly array $approvedRoleIds, public readonly array $availability, public readonly array $qualifications = [], public readonly ?string $qualificationsValidUntil = null, public readonly ?string $screeningValidUntil = null, public readonly bool $active = true,
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($personId);
        foreach ($approvedRoleIds as $roleId) {
            if (!is_string($roleId)) {
                throw new InvalidArgumentException('Invalid role ID.');
            } Input::id($roleId);
        }
        foreach ($qualifications as $qualification) {
            if (!is_string($qualification)) {
                throw new InvalidArgumentException('Qualifications must be strings.');
            } Input::text($qualification, 100);
        }
        if (null !== $qualificationsValidUntil) {
            Input::date($qualificationsValidUntil);
        }
        if (null !== $screeningValidUntil) {
            Input::date($screeningValidUntil);
        }
        foreach ($availability as $window) {
            if (!is_array($window) || !is_string($window['startsAt'] ?? null) || !is_string($window['endsAt'] ?? null)) {
                throw new InvalidArgumentException('Availability requires start and end timestamps.');
            }
            Input::interval($window['startsAt'], $window['endsAt']);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DomainException;

final class ChildCheckIn extends WorkflowRecord
{
    public function __construct(
        public readonly string $sessionId, public readonly string $childId, public readonly string $checkedInAt, public readonly string $checkedInBy, public readonly ?string $checkedOutAt = null, public readonly ?string $collectorId = null, public readonly ?string $checkedOutBy = null,
        public readonly bool $identityVerified = false, public readonly ?int $pickupLabelVersion = null,
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($sessionId);
        Input::id($childId);
        Input::id($checkedInBy);
        Input::instant($checkedInAt);
    }

    public function collect(string $collectorId, string $staffId, string $time, bool $identityVerified = false, ?int $pickupLabelVersion = null): self
    {
        if (null !== $this->checkedOutAt) {
            throw new DomainException('Child has already been collected.');
        }
        Input::id($collectorId);
        Input::id($staffId);
        Input::instant($time);

        return $this->changed(['collectorId' => $collectorId, 'checkedOutBy' => $staffId, 'checkedOutAt' => $time, 'identityVerified' => $identityVerified, 'pickupLabelVersion' => $pickupLabelVersion]);
    }
}

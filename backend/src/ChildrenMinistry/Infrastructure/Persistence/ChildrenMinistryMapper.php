<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Infrastructure\Persistence;

use App\ChildrenMinistry\Domain\Model\CheckInLabel;
use App\ChildrenMinistry\Domain\Model\ChildCheckIn;
use App\ChildrenMinistry\Domain\Model\ChildSupportNote;
use App\ChildrenMinistry\Domain\Model\Enrolment;
use App\ChildrenMinistry\Domain\Model\MinistryClass;
use App\ChildrenMinistry\Domain\Model\MinistrySession;
use App\ChildrenMinistry\Domain\Model\PickupAuthorisation;
use App\ChildrenMinistry\Domain\Model\Programme;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentMapper;

final class ChildrenMinistryMapper extends WorkflowDocumentMapper
{
    protected function types(): array
    {
        return ['labels' => CheckInLabel::class, 'supportnotes' => ChildSupportNote::class, 'programmes' => Programme::class, 'classes' => MinistryClass::class, 'enrolments' => Enrolment::class, 'authorisations' => PickupAuthorisation::class, 'sessions' => MinistrySession::class, 'checkins' => ChildCheckIn::class];
    }
}

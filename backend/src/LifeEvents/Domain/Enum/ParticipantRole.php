<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Enum;
enum ParticipantRole: string
{
    case SUBJECT='subject'; case SPOUSE='spouse'; case PROPOSER='proposer'; case RECIPIENT='recipient';
    case CHILD='child'; case PARENT='parent'; case DECEASED='deceased'; case GRADUATE='graduate';
    case BAPTIZED='baptized'; case OTHER='other';
}

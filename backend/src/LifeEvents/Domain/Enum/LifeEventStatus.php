<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Enum;
enum LifeEventStatus: string
{
    case ACTIVE='active'; case COMPLETED='completed'; case CANCELLED='cancelled';
}

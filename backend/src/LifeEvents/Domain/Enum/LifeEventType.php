<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Enum;
enum LifeEventType: string
{
    case BIRTH='birth'; case BAPTISM='baptism'; case CONFIRMATION='confirmation';
    case SALVATION='salvation'; case DEDICATION='dedication'; case MEMBERSHIP='membership';
    case ENGAGEMENT='engagement'; case MARRIAGE='marriage'; case GRADUATION='graduation';
    case FUNERAL='funeral'; case DEATH='death'; case OTHER='other';
}

<?php

declare(strict_types=1);

namespace App\Media\Domain;

/** What an image belongs to. The value is the stable text stored in `media_slots.owner_type`. */
enum OwnerType: string
{
    case Account = 'account';
    case Person = 'person';
    case Household = 'household';
    case Event = 'event';
    case Community = 'community';
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

use App\Communications\Application\Contract\DeliveryEvent;
use App\Communications\Application\Contract\DeliverySnapshot;

/** One delivery with its recipient, approved wording and paged history. Restricted: no incident or care content exists here. */
final readonly class EmailDeliveryView
{
    /** @param list<DeliveryEvent> $events */
    public function __construct(public DeliverySnapshot $delivery, public array $events)
    {
    }
}

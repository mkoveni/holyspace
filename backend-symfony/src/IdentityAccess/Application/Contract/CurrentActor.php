<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

/** The trusted caller as verified by the transport. Internal jobs supply their own adapter. */
interface CurrentActor
{
    public function current(): ?Actor;
}

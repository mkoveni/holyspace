<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetAcknowledgement;

use App\Giving\Application\Contract\AcknowledgementReader;
use App\Giving\Application\Contract\AcknowledgementView;
use App\Giving\Application\GivingAccess;
use App\Giving\Domain\GivingException;

final readonly class GetAcknowledgementHandler
{
    public function __construct(
        private GivingAccess $access,
        private AcknowledgementReader $reader,
    ) {
    }

    public function handle(GetAcknowledgementQuery $query): AcknowledgementView
    {
        $this->access->read();

        return $this->reader->get($query->acknowledgementId) ?? throw GivingException::missing();
    }
}

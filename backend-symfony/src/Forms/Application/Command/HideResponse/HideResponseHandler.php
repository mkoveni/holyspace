<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\HideResponse;

use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\FormAccess;
use App\Forms\Domain\FormException;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Removes one entry from the leaderboard (a rude nickname); the response itself stays. */
final readonly class HideResponseHandler implements CommandHandler
{
    public function __construct(private FormAccess $access, private FormReader $reader, private FormRepository $forms, private ClockInterface $clock)
    {
    }

    public function handle(HideResponseCommand $c): null
    {
        $this->access->moderate($c->actorId);
        if (!$this->reader->hideFromLeaderboard($c->formId, $c->responseId)) {
            throw FormException::missing();
        }
        $this->forms->audit($c->formId, 'ENTRY_HIDDEN', '', $c->actorId, $this->clock->now());

        return null;
    }
}

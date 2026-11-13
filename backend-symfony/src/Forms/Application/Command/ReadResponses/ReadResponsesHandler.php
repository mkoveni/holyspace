<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\ReadResponses;

use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\FormAccess;
use App\Forms\Domain\FormException;
use App\Forms\Domain\IdentityMode;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Individual responses. Reading who answered an identified form is restricted data, so it is a command: the audit row
 * commits with the read. Anonymous responses carry no respondent and no time of day, so there is nothing to audit.
 */
final readonly class ReadResponsesHandler implements CommandHandler
{
    public function __construct(private FormAccess $access, private FormReader $reader, private FormRepository $forms, private ClockInterface $clock)
    {
    }

    public function handle(ReadResponsesCommand $c): ResponsePage
    {
        $this->access->readResponses($c->actorId);
        $form = $this->reader->find($c->formId) ?? throw FormException::missing();
        $identified = IdentityMode::Identified->value === $form->identity;
        if ($identified) {
            $this->forms->audit($form->id, 'RESPONSES_READ', 'offset '.$c->offset, $c->actorId, $this->clock->now());
        }

        return new ResponsePage(
            $this->reader->responses($form->id, max(1, min(200, $c->limit)), max(0, $c->offset)),
            $form->responseCount,
            $identified,
        );
    }
}

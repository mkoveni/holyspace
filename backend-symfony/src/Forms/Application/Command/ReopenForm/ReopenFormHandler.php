<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\ReopenForm;

use App\Forms\Application\FormAccess;
use App\Forms\Domain\FormException;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class ReopenFormHandler implements CommandHandler
{
    public function __construct(private FormAccess $access, private FormRepository $forms, private ClockInterface $clock)
    {
    }

    public function handle(ReopenFormCommand $c): null
    {
        $this->access->manage($c->actorId);
        $form = $this->forms->findForUpdate($c->formId) ?? throw FormException::missing();
        $now = $this->clock->now();
        $this->forms->save($form->reopen($c->expectedVersion, $now));
        $this->forms->audit($form->id, 'REOPENED', '', $c->actorId, $now);

        return null;
    }
}

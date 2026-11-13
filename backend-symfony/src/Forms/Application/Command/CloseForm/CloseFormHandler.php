<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\CloseForm;

use App\Forms\Application\FormAccess;
use App\Forms\Domain\FormException;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class CloseFormHandler implements CommandHandler
{
    public function __construct(private FormAccess $access, private FormRepository $forms, private ClockInterface $clock)
    {
    }

    public function handle(CloseFormCommand $c): null
    {
        $this->access->manage($c->actorId);
        $form = $this->forms->findForUpdate($c->formId) ?? throw FormException::missing();
        $now = $this->clock->now();
        $this->forms->save($form->close($c->expectedVersion, $now));
        $this->forms->audit($form->id, 'CLOSED', '', $c->actorId, $now);

        return null;
    }
}

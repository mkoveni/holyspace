<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\UpdateForm;

use App\Forms\Application\FormAccess;
use App\Forms\Domain\FormException;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class UpdateFormHandler implements CommandHandler
{
    public function __construct(private FormAccess $access, private FormRepository $forms, private ClockInterface $clock)
    {
    }

    public function handle(UpdateFormCommand $c): null
    {
        $this->access->manage($c->actorId);
        $form = $this->forms->findForUpdate($c->formId) ?? throw FormException::missing();
        $now = $this->clock->now();
        $this->forms->save($form->edit($c->draft->settings(), $c->expectedVersion, $now));
        $this->forms->audit($form->id, 'EDITED', '', $c->actorId, $now);

        return null;
    }
}

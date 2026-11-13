<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\CreateForm;

use App\Forms\Application\FormAccess;
use App\Forms\Domain\Form;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Idempotent on the request id: a retry returns the same form. */
final readonly class CreateFormHandler implements CommandHandler
{
    public function __construct(private FormAccess $access, private FormRepository $forms, private ClockInterface $clock)
    {
    }

    /** @return string the form id, which is the request id */
    public function handle(CreateFormCommand $c): string
    {
        $this->access->manage($c->actorId);
        $existing = $this->forms->findForUpdate($c->requestId);
        if (null !== $existing) {
            return $existing->id;
        }
        $now = $this->clock->now();
        $form = Form::create($c->requestId, $c->actorId, $c->draft->settings(), $now);
        $this->forms->insert($form);
        $this->forms->audit($form->id, 'CREATED', '', $c->actorId, $now);

        return $form->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\PublishForm;

use App\Forms\Application\Contract\FormKeys;
use App\Forms\Application\FormAccess;
use App\Forms\Domain\Audience;
use App\Forms\Domain\FormException;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Freezes the draft as a new version. A public form gets a new link token; only its hash is stored, so it is returned once. */
final readonly class PublishFormHandler implements CommandHandler
{
    public function __construct(private FormAccess $access, private FormRepository $forms, private FormKeys $keys, private ClockInterface $clock)
    {
    }

    public function handle(PublishFormCommand $c): ?string
    {
        $this->access->manage($c->actorId);
        $form = $this->forms->findForUpdate($c->formId) ?? throw FormException::missing();
        $now = $this->clock->now();
        $token = Audience::Public === $form->settings->audience ? $this->keys->newToken() : null;
        $published = $form->publish(null === $token ? null : $this->keys->tokenHash($token), $c->expectedVersion, $now);
        $this->forms->save($published);
        $this->forms->publishVersion($published, $now);
        $this->forms->audit($form->id, 'PUBLISHED', 'version '.$published->currentVersion, $c->actorId, $now);

        return $token;
    }
}

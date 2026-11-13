<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetPublicForm;

use App\Forms\Application\Contract\FormKeys;
use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\FormPage;
use App\Forms\Application\FormPages;
use App\Forms\Domain\FormException;
use Psr\Clock\ClockInterface;

/** A bad, unpublished or rotated link is simply "not found", so links cannot be probed. */
final readonly class GetPublicFormHandler
{
    public function __construct(private FormReader $reader, private FormKeys $keys, private ClockInterface $clock)
    {
    }

    public function handle(GetPublicFormQuery $q): FormPage
    {
        $now = $this->clock->now();
        $form = $this->reader->publishedByTokenHash($this->keys->tokenHash($q->token), $now) ?? throw FormException::missing();

        return FormPages::of($form, $now, $this->keys->challenge($form->id, $now));
    }
}

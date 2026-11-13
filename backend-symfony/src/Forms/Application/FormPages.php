<?php

declare(strict_types=1);

namespace App\Forms\Application;

use App\Forms\Application\Contract\PublishedForm;
use App\Forms\Domain\FormDefinition;
use App\Forms\Domain\FormKind;
use DateTimeImmutable;

/** Builds the respondent-facing page. The definition goes through its projection, so no answer key can be served. */
final class FormPages
{
    private function __construct()
    {
    }

    public static function of(PublishedForm $form, DateTimeImmutable $now, ?string $challenge): FormPage
    {
        $definition = FormDefinition::fromArray($form->definition, FormKind::Quiz->value === $form->kind);

        return new FormPage(
            $form->id,
            $form->title,
            $form->description,
            $form->kind,
            $form->identity,
            $form->version,
            $definition->projection(),
            $form->acceptsAt($now),
            $form->retentionMonths,
            $form->leaderboardEnabled,
            $challenge,
        );
    }
}

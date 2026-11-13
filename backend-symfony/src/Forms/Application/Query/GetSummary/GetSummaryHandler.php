<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetSummary;

use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\FormAccess;
use App\Forms\Domain\FormDefinition;
use App\Forms\Domain\FormException;
use App\Forms\Domain\FormKind;
use App\Forms\Domain\IdentityMode;

/**
 * Aggregates over the stored responses. On an anonymous form nothing is shown until enough people have answered, so a
 * summary of a tiny group cannot reveal one person's answers.
 */
final readonly class GetSummaryHandler
{
    public const int MINIMUM_ANONYMOUS_RESPONSES = 5;

    public function __construct(private FormAccess $access, private FormReader $reader)
    {
    }

    public function handle(GetSummaryQuery $q): FormSummaryReport
    {
        $this->access->viewResponses();
        $form = $this->reader->find($q->formId) ?? throw FormException::missing();
        $anonymous = IdentityMode::Anonymous->value === $form->identity;
        if ($anonymous && $form->responseCount < self::MINIMUM_ANONYMOUS_RESPONSES) {
            return new FormSummaryReport($form->responseCount, true, self::MINIMUM_ANONYMOUS_RESPONSES, []);
        }
        $raw = $form->publishedVersion > 0 ? $this->reader->publishedDefinition($form->id, $form->publishedVersion) : null;
        if (null === $raw) {
            return new FormSummaryReport($form->responseCount, false, self::MINIMUM_ANONYMOUS_RESPONSES, []);
        }
        $definition = FormDefinition::fromArray($raw, FormKind::Quiz->value === $form->kind);

        return new FormSummaryReport($form->responseCount, false, self::MINIMUM_ANONYMOUS_RESPONSES, SummaryBuilder::build($definition, $this->reader->answerSets($form->id)));
    }
}

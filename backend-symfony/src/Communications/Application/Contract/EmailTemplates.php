<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** Rendering of the last published revision of a template, for originating contexts. */
interface EmailTemplates
{
    /**
     * @param array<string, string> $values every declared variable, exactly
     *
     * @throws \App\Communications\Domain\CommunicationException when no template is published or a value is missing
     */
    public function render(string $key, array $values): RenderedEmail;
}

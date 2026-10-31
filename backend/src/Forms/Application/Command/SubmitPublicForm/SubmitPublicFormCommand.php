<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\SubmitPublicForm;

use App\Shared\Application\CQRS\Command;

final readonly class SubmitPublicFormCommand implements Command
{
    public function __construct(
        public string $token,
        public int $version,
        public array $answers,
        public string $submissionKey,
    ) {
    }
}

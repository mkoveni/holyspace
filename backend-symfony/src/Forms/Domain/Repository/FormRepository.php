<?php

declare(strict_types=1);

namespace App\Forms\Domain\Repository;

use App\Forms\Domain\Form;
use App\Forms\Domain\FormDefinition;
use DateTimeImmutable;

interface FormRepository
{
    public function insert(Form $form): void;

    /** Saves settings, status and audience groups at the next version; a stale stored version is a conflict. */
    public function save(Form $form): void;

    /** Freezes the form's draft as its current numbered version. */
    public function publishVersion(Form $form, DateTimeImmutable $now): void;

    public function findForUpdate(string $id): ?Form;

    /** Locks the form row, so concurrent submissions serialise on the response limit. */
    public function findByTokenHashForUpdate(string $tokenHash): ?Form;

    public function definition(string $formId, int $version): ?FormDefinition;

    public function audit(string $formId, string $action, string $detail, ?string $actorId, DateTimeImmutable $at): void;
}

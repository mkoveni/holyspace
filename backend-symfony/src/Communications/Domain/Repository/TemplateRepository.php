<?php

declare(strict_types=1);

namespace App\Communications\Domain\Repository;

use App\Communications\Domain\EmailTemplate;

interface TemplateRepository
{
    public function lock(string $id): ?EmailTemplate;

    /** A key already in use is a 409. */
    public function insert(EmailTemplate $template): void;

    public function save(EmailTemplate $template): void;

    /** Records the published revision of the template. */
    public function publish(EmailTemplate $template): void;
}

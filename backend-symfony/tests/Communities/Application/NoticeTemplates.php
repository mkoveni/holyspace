<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Communications\Application\Contract\EmailTemplates;
use App\Communications\Application\Contract\RenderedEmail;

/** Renders every template as its values joined, at a fixed revision. */
final class NoticeTemplates implements EmailTemplates
{
    public int $version = 0;

    public function render(string $key, array $values): RenderedEmail
    {
        return new RenderedEmail($key, $this->version, 'A message from '.$values['community'], implode('|', $values));
    }
}

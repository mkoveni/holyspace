<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * Marks a use case that changes state. A handler exposes one `handle(XCommand): result` method and
 * never opens its own transaction: the bus wraps the whole call, so the change and its audit row
 * commit or roll back together. Queries are not handlers of this kind; call them directly.
 */
interface CommandHandler
{
}

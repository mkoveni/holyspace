<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * A request to change state. Commands are plain readonly records; the bus runs the matching
 * CommandHandler inside one transaction and returns its result.
 *
 * @template-covariant TResult
 */
interface Command
{
}

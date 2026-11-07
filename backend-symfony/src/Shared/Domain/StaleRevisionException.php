<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/** Optimistic-concurrency failure: the caller edited an out-of-date copy of the record. */
final class StaleRevisionException extends ContextException
{
    public function __construct()
    {
        parent::__construct(ErrorKind::Conflict, 'The record has changed. Reload and try again.');
    }
}

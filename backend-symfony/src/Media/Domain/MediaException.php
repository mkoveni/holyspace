<?php

declare(strict_types=1);

namespace App\Media\Domain;

use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;

final class MediaException extends ContextException
{
    public function __construct(ErrorKind $kind, string $message, private readonly int $retryAfter = 60)
    {
        parent::__construct($kind, $message);
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfter;
    }

    public static function invalid(string $message): self
    {
        return new self(ErrorKind::Invalid, $message);
    }

    public static function forbidden(): self
    {
        return new self(ErrorKind::Forbidden, 'You do not have permission to use this image.');
    }

    public static function missing(): self
    {
        return new self(ErrorKind::NotFound, 'The requested image was not found.');
    }

    public static function throttled(int $retryAfterSeconds): self
    {
        return new self(ErrorKind::Throttled, 'Too many uploads. Try again shortly.', max(1, $retryAfterSeconds));
    }

    /** The file system could not be read or written; the client may retry. */
    public static function storageUnavailable(): self
    {
        return new self(ErrorKind::Unavailable, 'Image storage is not available. Try again shortly.');
    }
}

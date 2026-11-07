<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

use DateTimeImmutable;

/** An active People email contact and, when it has a current verification, the fingerprint approval must quote. */
final readonly class EmailRecipient
{
    public function __construct(public string $contactId, public string $email, public ?string $verificationId, public ?DateTimeImmutable $validUntil, public ?string $reviewHash)
    {
    }
}

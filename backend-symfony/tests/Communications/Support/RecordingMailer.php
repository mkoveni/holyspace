<?php

declare(strict_types=1);

namespace App\Tests\Communications\Support;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Throwable;

/** A mailer that keeps what it was given, or fails the way the test asks. */
final class RecordingMailer implements MailerInterface
{
    /** @var list<Email> */
    public array $sent = [];

    public function __construct(private readonly ?Throwable $failure = null)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }
        \assert($message instanceof Email);
        $this->sent[] = $message;
    }
}

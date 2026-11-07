<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailTransport;
use App\Communications\Application\Contract\TransportConfiguration;
use App\Communications\Application\Contract\TransportResult;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;

/**
 * Sends one reviewed message to one reviewed recipient through Symfony Mailer with a stable Message-ID. In capture
 * mode it refuses any host except a local test inbox, so test mail can never leave the machine.
 */
final readonly class MailerEmailTransport implements EmailTransport
{
    private const array LOCAL_HOSTS = ['localhost', '127.0.0.1', 'mailpit', '::1', '[::1]'];

    public function __construct(
        private MailerInterface $mailer,
        private bool $enabled,
        private bool $capture,
        private string $from,
        private string $mailerDsn,
    ) {
    }

    public function configuration(): TransportConfiguration
    {
        $host = parse_url($this->mailerDsn, \PHP_URL_HOST);
        $local = \is_string($host) && \in_array(strtolower($host), self::LOCAL_HOSTS, true);

        return new TransportConfiguration($this->enabled && '' !== trim($this->from) && (!$this->capture || $local), $this->capture, $this->capture ? 'Local test inbox' : 'SMTP');
    }

    public function send(DeliverySnapshot $delivery): TransportResult
    {
        if (!$this->configuration()->enabled) {
            return new TransportResult('FAILED', 'NOT_CONFIGURED', false);
        }
        try {
            $email = new Email()
                ->from(new Address($this->from))
                ->to(new Address($delivery->recipient))
                ->subject($delivery->subject);
            'text/html' === $delivery->contentType ? $email->html($delivery->body, 'utf-8') : $email->text($delivery->body, 'utf-8');
            $email->getHeaders()->addIdHeader('Message-ID', trim($delivery->messageId, '<>'));
            $this->mailer->send($email);

            return new TransportResult('SENT', '', $this->capture);
        } catch (MimeException) {
            return new TransportResult('FAILED', 'INVALID_EMAIL', false);
        } catch (TransportExceptionInterface $error) {
            return str_contains(strtolower($error->getMessage()), 'authenticate')
                ? new TransportResult('FAILED', 'SMTP_AUTHENTICATION', false)
                : new TransportResult('UNKNOWN', 'SMTP_UNCERTAIN', false);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Communications\Infrastructure;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Infrastructure\MailerEmailTransport;
use App\Tests\Communications\Support\RecordingMailer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Throwable;

final class MailerEmailTransportTest extends TestCase
{
    private function delivery(string $contentType = 'text/plain', string $recipient = 'guardian@example.org'): DeliverySnapshot
    {
        $now = new DateTimeImmutable('2026-06-01T10:00:00Z');
        $id = '11111111-1111-4111-8111-111111111111';

        return new DeliverySnapshot($id, 'c', 'v', 'k', $recipient, 'Ministry reminder', 'Hello guardian, welcome to ministry. Zoë', str_repeat('a', 64), 'a', $now, $now, 'SENDING', 1, '<'.$id.'@onechurch.delivery>', 'attempt', $now, null, '', false, 1, $contentType);
    }

    private function mailer(?Throwable $failure = null): RecordingMailer
    {
        return new RecordingMailer($failure);
    }

    private function transport(RecordingMailer $mailer, bool $enabled = true, bool $capture = true, string $from = 'ministry@onechurch.test', string $dsn = 'smtp://localhost:1025'): MailerEmailTransport
    {
        return new MailerEmailTransport($mailer, $enabled, $capture, $from, $dsn);
    }

    /** @return iterable<string, array{string}> */
    public static function contentTypes(): iterable
    {
        yield 'plain' => ['text/plain'];
        yield 'html' => ['text/html'];
    }

    #[DataProvider('contentTypes')]
    public function testOneReviewedRecipientUtf8AndAStableMessageId(string $contentType): void
    {
        $mailer = $this->mailer();
        $result = $this->transport($mailer)->send($this->delivery($contentType));

        self::assertSame(['SENT', '', true], [$result->status, $result->errorCode, $result->captured]);
        $email = $mailer->sent[0];
        self::assertSame(['guardian@example.org'], array_map(static fn ($a): string => $a->getAddress(), $email->getTo()));
        self::assertSame([], [...$email->getCc(), ...$email->getBcc()]);
        self::assertSame('ministry@onechurch.test', $email->getFrom()[0]->getAddress());
        self::assertSame('Ministry reminder', $email->getSubject());
        self::assertSame('<11111111-1111-4111-8111-111111111111@onechurch.delivery>', $email->getHeaders()->get('Message-ID')?->getBodyAsString());
        $body = 'text/html' === $contentType ? $email->getHtmlBody() : $email->getTextBody();
        self::assertSame('Hello guardian, welcome to ministry. Zoë', $body);
        self::assertSame('text/html' === $contentType ? null : null, 'text/html' === $contentType ? $email->getTextBody() : $email->getHtmlBody());
        self::assertSame('utf-8', 'text/html' === $contentType ? $email->getHtmlCharset() : $email->getTextCharset());
    }

    public function testCaptureModeCannotSendThroughAnExternalMailHost(): void
    {
        $mailer = $this->mailer();
        foreach (['smtp://smtp.external.example:587', 'null://null', 'native://default'] as $dsn) {
            $transport = $this->transport($mailer, dsn: $dsn);
            self::assertFalse($transport->configuration()->enabled, $dsn);
            self::assertSame(['FAILED', 'NOT_CONFIGURED', false], [$transport->send($this->delivery())->status, $transport->send($this->delivery())->errorCode, $transport->send($this->delivery())->captured]);
        }
        self::assertSame([], $mailer->sent);
        foreach (['smtp://localhost:1025', 'smtp://127.0.0.1:25', 'smtp://mailpit:1025', 'smtp://[::1]:25'] as $dsn) {
            self::assertTrue($this->transport($mailer, dsn: $dsn)->configuration()->enabled, $dsn);
        }
    }

    public function testLiveModeMayUseAnyHostButNeedsASender(): void
    {
        $mailer = $this->mailer();

        self::assertTrue($this->transport($mailer, capture: false, dsn: 'smtp://smtp.example.org:587')->configuration()->enabled);
        self::assertFalse($this->transport($mailer, capture: false, from: '  ', dsn: 'smtp://smtp.example.org')->configuration()->enabled);
        self::assertFalse($this->transport($mailer, enabled: false)->configuration()->enabled);
        self::assertSame(['SMTP', false], [$this->transport($mailer, capture: false)->configuration()->mode, $this->transport($mailer, capture: false)->configuration()->capture]);
        self::assertSame('Local test inbox', $this->transport($mailer)->configuration()->mode);
        self::assertFalse($this->transport($mailer, capture: false, dsn: 'smtp://smtp.example.org')->send($this->delivery())->captured, 'live sends are never marked captured');
    }

    public function testADisabledTransportSendsNothing(): void
    {
        $mailer = $this->mailer();

        $result = $this->transport($mailer, enabled: false)->send($this->delivery());

        self::assertSame(['FAILED', 'NOT_CONFIGURED'], [$result->status, $result->errorCode]);
        self::assertSame([], $mailer->sent);
    }

    public function testAnInvalidRecipientIsADefiniteFailure(): void
    {
        $mailer = $this->mailer();
        $result = $this->transport($mailer)->send($this->delivery(recipient: 'not an address'));

        self::assertSame(['FAILED', 'INVALID_EMAIL'], [$result->status, $result->errorCode]);
        self::assertSame([], $mailer->sent);
    }

    public function testRejectedCredentialsAreDefiniteAndOtherTransportErrorsAreUncertain(): void
    {
        $auth = $this->transport($this->mailer(new TransportException('Failed to authenticate on SMTP server with username "x".')), capture: false)->send($this->delivery());
        $other = $this->transport($this->mailer(new TransportException('Connection timed out.')), capture: false)->send($this->delivery());

        self::assertSame(['FAILED', 'SMTP_AUTHENTICATION', false], [$auth->status, $auth->errorCode, $auth->captured]);
        self::assertSame(['UNKNOWN', 'SMTP_UNCERTAIN', false], [$other->status, $other->errorCode, $other->captured]);
    }
}

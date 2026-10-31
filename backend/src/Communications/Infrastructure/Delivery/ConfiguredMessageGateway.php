<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Delivery;

use App\Communications\Application\Port\DeliveryConfiguration;
use App\Communications\Application\Port\MessageGateway;
use DomainException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(MessageGateway::class)]
#[AsAlias(DeliveryConfiguration::class)]
final readonly class ConfiguredMessageGateway implements MessageGateway
{
    public function __construct(
        private MailerInterface $mailer,

        private HttpClientInterface $http,

        #[Autowire(env: 'MAILER_DSN')]
        private string $mailerDsn,

        #[Autowire(env: 'MAILER_FROM')]
        private string $from,

        #[Autowire(env: 'SMS_API_URL')]
        private string $smsUrl,

        #[Autowire(env: 'SMS_API_TOKEN')]
        private string $smsToken,
    ) {
    }

    public function configuration(): array
    {
        return ['email' => '' !== $this->mailerDsn && !str_starts_with($this->mailerDsn, 'null://'), 'sms' => str_starts_with($this->smsUrl, 'https://') && '' !== $this->smsToken];
    }

    public function send(string $deliveryId, string $channel, string $destination, string $subject, string $body): void
    {
        if (!($this->configuration()[$channel] ?? false)) {
            throw new DomainException('Channel provider is not configured.');
        }
        if ('email' === $channel) {
            $this->mailer->send((new Email())->from($this->from)->to($destination)->subject($subject)->text($body));

            return;
        }
        if (!str_starts_with($this->smsUrl, 'https://')) {
            throw new DomainException('SMS provider endpoint must use HTTPS.');
        }
        $response = $this->http->request('POST', $this->smsUrl, ['auth_bearer' => $this->smsToken, 'headers' => ['Idempotency-Key' => $deliveryId], 'json' => ['to' => $destination, 'message' => $body, 'reference' => $deliveryId], 'timeout' => 15]);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException('SMS provider rejected the message.');
        }
    }
}

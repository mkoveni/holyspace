<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Domain\PaymentException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Java `JdkProviderHttp` on Symfony HttpClient: 10-second timeout, no redirects, 2xx only, at most 1 MB. */
final readonly class SymfonyProviderHttp implements ProviderHttp
{
    private const int MAX_BODY = 1_000_000;

    public function __construct(private HttpClientInterface $http)
    {
    }

    public function send(string $method, string $url, array $headers, string $body): string
    {
        $options = ['headers' => $headers, 'timeout' => 10, 'max_duration' => 17, 'max_redirects' => 0];
        if ('' !== $body) {
            $options['body'] = $body;
        }
        try {
            $response = $this->http->request($method, $url, $options);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface) {
            throw PaymentException::unavailable('Payment provider is temporarily unavailable. Retry the same request.');
        }
        if ($status < 200 || $status > 299 || \strlen($content) > self::MAX_BODY) {
            throw PaymentException::unavailable('Provider request could not be confirmed. Retry the same request.');
        }

        return $content;
    }
}

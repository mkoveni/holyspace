<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

/** One provider HTTPS request (Java `ProviderHttp`). Never called inside a database transaction. */
interface ProviderHttp
{
    /**
     * @param array<string, string> $headers
     *
     * @return string the body of a 2xx response; anything else is a 503 PaymentException
     */
    public function send(string $method, string $url, array $headers, string $body): string;
}

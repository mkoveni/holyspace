<?php

declare(strict_types=1);

namespace App\Tests\Payments\Infrastructure;

use App\Payments\Domain\PaymentException;
use App\Payments\Infrastructure\Gateway\ProviderHttp;
use App\Payments\Infrastructure\Gateway\TurnstileHumanCheck;
use PHPUnit\Framework\TestCase;

final class TurnstileHumanCheckTest extends TestCase
{
    /** @return ProviderHttp&object{body: string|null} */
    private function http(string $answer): ProviderHttp
    {
        return new class ($answer) implements ProviderHttp {
            public ?string $body = null;

            public function __construct(private readonly string $answer)
            {
            }

            public function send(string $method, string $url, array $headers, string $body): string
            {
                $this->body = $body;

                return $this->answer;
            }
        };
    }

    public function testWithNoSecretProductionSaysItIsNotSetUpInsteadOfCallingTheGiverARobot(): void
    {
        $check = new TurnstileHumanCheck($this->http('{}'), '', 'prod');
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('not fully set up');
        $check->passes('token', '1.2.3.4');
    }

    public function testWithNoSecretOtherEnvironmentsSkipTheCheck(): void
    {
        foreach (['dev', 'test'] as $environment) {
            self::assertTrue(new TurnstileHumanCheck($this->http('{}'), '', $environment)->passes(null, '1.2.3.4'));
        }
    }

    public function testWithASecretAMissingTokenFailsWithoutCallingCloudflare(): void
    {
        $http = $this->http('{"success":true}');
        $check = new TurnstileHumanCheck($http, 'secret', 'prod');
        self::assertFalse($check->passes(null, '1.2.3.4'));
        self::assertFalse($check->passes('', '1.2.3.4'));
        self::assertNull($http->body);
    }

    public function testCloudflareDecidesAndTheSecretAndAddressAreSent(): void
    {
        $yes = $this->http('{"success":true}');
        self::assertTrue(new TurnstileHumanCheck($yes, 'secret', 'prod')->passes('tok', '1.2.3.4'));
        parse_str((string) $yes->body, $sent);
        self::assertSame(['secret' => 'secret', 'response' => 'tok', 'remoteip' => '1.2.3.4'], $sent);
        self::assertFalse(new TurnstileHumanCheck($this->http('{"success":false}'), 'secret', 'prod')->passes('tok', '1.2.3.4'));
    }
}

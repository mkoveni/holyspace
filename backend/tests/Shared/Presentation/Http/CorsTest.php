<?php

declare(strict_types=1);

namespace App\Tests\Shared\Presentation\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Dotenv\Dotenv;

final class CorsTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        (new Dotenv())->loadEnv(dirname(__DIR__, 4).'/.env', overrideExistingVars: false);
    }

    public function testFrontendPreflightAllowsJsonAndBearerTokens(): void
    {
        $client = self::createClient(['environment' => 'test']);
        $client->request('OPTIONS', '/api/people', server: ['HTTP_ORIGIN' => 'http://localhost:5173', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST', 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,authorization,if-match']);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
        self::assertStringContainsString('POST', $client->getResponse()->headers->get('Access-Control-Allow-Methods'));
        self::assertStringContainsString('authorization', strtolower($client->getResponse()->headers->get('Access-Control-Allow-Headers')));
        self::assertStringContainsString('if-match', strtolower($client->getResponse()->headers->get('Access-Control-Allow-Headers')));
        self::assertStringContainsString('content-type', strtolower($client->getResponse()->headers->get('Access-Control-Allow-Headers')));
    }

    public function testOtherOriginsAreNotAllowed(): void
    {
        $client = self::createClient(['environment' => 'test']);
        $client->request('OPTIONS', '/api/people', server: ['HTTP_ORIGIN' => 'http://localhost:5174', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET']);
        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testAuthenticationErrorsIncludeCorsHeaders(): void
    {
        $client = self::createClient(['environment' => 'test']);
        $client->request('GET', '/api/people', server: ['HTTP_ORIGIN' => 'http://localhost:5173']);
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}

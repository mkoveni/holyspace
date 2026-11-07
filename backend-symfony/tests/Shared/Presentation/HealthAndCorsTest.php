<?php

declare(strict_types=1);

namespace App\Tests\Shared\Presentation;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthAndCorsTest extends WebTestCase
{
    public function testHealthReportsUpWhenDatabaseIsReachable(): void
    {
        $client = self::createClient();
        $client->request('GET', '/actuator/health');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('{"status":"UP"}', $client->getResponse()->getContent());
    }

    public function testUnknownRouteReturnsJsonMessage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/nothing-here');

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('"message"', (string) $client->getResponse()->getContent());
    }

    public function testPreflightFromLocalFrontendIsAllowed(): void
    {
        $client = self::createClient();
        $client->request('OPTIONS', '/api/anything', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);

        $headers = $client->getResponse()->headers;
        self::assertSame('http://localhost:5173', $headers->get('Access-Control-Allow-Origin'));
        self::assertNull($headers->get('Access-Control-Allow-Credentials'));
        self::assertSame('3600', $headers->get('Access-Control-Max-Age'));
    }

    public function testPreflightFromUnknownOriginIsNotAllowed(): void
    {
        $client = self::createClient();
        $client->request('OPTIONS', '/api/anything', server: [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        self::assertNull($client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }
}

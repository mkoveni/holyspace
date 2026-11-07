<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class KernelBootTest extends KernelTestCase
{
    public function testKernelBootsAndDbalConnectionIsWired(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);

        self::assertInstanceOf(Connection::class, $connection);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Media\Domain;

use App\Media\Domain\ImagePolicy;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ImagePolicyTest extends TestCase
{
    public function testRejectsNonPositiveSizes(): void
    {
        $this->expectException(DomainException::class);
        new ImagePolicy(0, 100, []);
    }

    public function testRejectsVariantsThatAreNotAscendingOrNotSmaller(): void
    {
        foreach ([[100, 50], [50, 50], [100], [200]] as $widths) {
            try {
                new ImagePolicy(100, 100, $widths);
                self::fail('Expected '.json_encode($widths).' to be refused.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }
}

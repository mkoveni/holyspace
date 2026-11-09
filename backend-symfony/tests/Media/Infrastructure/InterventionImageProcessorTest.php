<?php

declare(strict_types=1);

namespace App\Tests\Media\Infrastructure;

use App\Media\Domain\ImageKind;
use App\Media\Domain\ImagePolicy;
use App\Media\Domain\MediaException;
use App\Media\Infrastructure\InterventionImageProcessor;
use PHPUnit\Framework\TestCase;

/** The real GD driver on real bytes: what is accepted, what the output looks like, and what must not survive. */
final class InterventionImageProcessorTest extends TestCase
{
    private InterventionImageProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new InterventionImageProcessor();
    }

    public function testJpegPngAndWebpAreCroppedToTheKindsExactSizeAndStoredAsWebp(): void
    {
        $policy = ImageKind::Event->policy();
        foreach ([ImageFixtures::jpeg(400, 300), ImageFixtures::png(400, 300), ImageFixtures::webp(400, 300), ImageFixtures::png(2000, 500)] as $upload) {
            $result = $this->processor->process($upload, $policy);

            self::assertSame([1280, 720], [$result->width, $result->height]);
            self::assertSame(['RIFF', 'WEBP'], [substr($result->bytes, 0, 4), substr($result->bytes, 8, 4)]);
            $size = getimagesizefromstring($result->bytes);
            self::assertSame([1280, 720, \IMAGETYPE_WEBP], [$size[0] ?? 0, $size[1] ?? 0, $size[2] ?? 0]);
        }
    }

    public function testSmallerCopiesKeepTheAspectRatio(): void
    {
        $result = $this->processor->process(ImageFixtures::png(), ImageKind::Event->policy());

        self::assertSame([640], array_keys($result->variants));
        $size = getimagesizefromstring($result->variants[640]);
        self::assertSame([640, 360], [$size[0] ?? 0, $size[1] ?? 0]);
    }

    public function testAKindWithNoVariantsProducesNone(): void
    {
        self::assertSame([], $this->processor->process(ImageFixtures::png(), new ImagePolicy(40, 40, []))->variants);
    }

    public function testCameraOrientationIsAppliedBeforeCropping(): void
    {
        // Stored 60x30, left half red; orientation 6 means "rotate 90 degrees clockwise", so red ends up on top.
        $result = $this->processor->process(ImageFixtures::jpegWithMetadata(6, 'x'), new ImagePolicy(40, 80, []));

        [$redTop] = ImageFixtures::pixel($result->bytes, 20, 10);
        [, , $blueBottom] = ImageFixtures::pixel($result->bytes, 20, 70);
        self::assertGreaterThan(200, $redTop);
        self::assertGreaterThan(200, $blueBottom);
    }

    public function testAnUprightPictureIsNotRotated(): void
    {
        $result = $this->processor->process(ImageFixtures::jpegWithMetadata(1, 'x'), new ImagePolicy(40, 80, []));

        [$redTop] = ImageFixtures::pixel($result->bytes, 5, 10);
        [$redBottom] = ImageFixtures::pixel($result->bytes, 5, 70);
        self::assertGreaterThan(200, $redTop);
        self::assertGreaterThan(200, $redBottom, 'left stays left: only an orientation tag rotates it');
    }

    public function testNoMetadataSurvivesReEncoding(): void
    {
        $upload = ImageFixtures::jpegWithMetadata(1, 'SECRET-GPS-POSITION');
        self::assertStringContainsString('SECRET-GPS-POSITION', $upload);

        $result = $this->processor->process($upload, ImageKind::Person->policy());

        self::assertStringNotContainsString('SECRET-GPS-POSITION', $result->bytes);
        self::assertStringNotContainsString('Exif', $result->bytes);
        self::assertStringNotContainsString('SECRET-GPS-POSITION', implode('', $result->variants));
    }

    public function testBytesThatAreNotAnAcceptedImageAreRefused(): void
    {
        foreach (['' => 'Choose an image file.', 'plain text' => 'Choose a JPEG, PNG or WebP image.', '<svg xmlns="http://www.w3.org/2000/svg"/>' => 'Choose a JPEG, PNG or WebP image.', ImageFixtures::gif() => 'Choose a JPEG, PNG or WebP image.'] as $bytes => $message) {
            $this->assertRefused((string) $bytes, $message);
        }
    }

    public function testALabelledImageWithOtherContentIsJudgedByItsBytes(): void
    {
        $this->assertRefused("\xFF\xD8\xFF\xE0 not really a jpeg", 'Choose a JPEG, PNG or WebP image.');
    }

    public function testATruncatedImageIsRefusedNotCrashed(): void
    {
        $this->assertRefused(substr(ImageFixtures::jpeg(400, 300), 0, 300), 'This image could not be read.');
    }

    public function testOversizeDimensionsAndPixelCountsAreRefusedBeforeDecoding(): void
    {
        $this->assertRefused(ImageFixtures::hugePng(8001, 10), 'at most 8000 pixels');
        $this->assertRefused(ImageFixtures::hugePng(6000, 5000), '25 million pixels');
    }

    public function testMoreThanFiveMegabytesIsRefused(): void
    {
        $this->assertRefused(str_repeat('x', ImagePolicy::MAX_SOURCE_BYTES + 1), 'at most 5 MB');
    }

    private function assertRefused(string $bytes, string $messagePart): void
    {
        try {
            $this->processor->process($bytes, ImageKind::Event->policy());
        } catch (MediaException $e) {
            self::assertStringContainsString($messagePart, $e->getMessage());

            return;
        }
        self::fail('Expected the upload to be refused: '.$messagePart);
    }
}

<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

use App\Media\Domain\ImagePolicy;

interface ImageProcessor
{
    /**
     * Checks an upload from its bytes (never its declared type or name), crops it to the policy's size, re-encodes it
     * so no metadata survives, and prepares the policy's smaller copies.
     *
     * @throws \App\Media\Domain\MediaException when the bytes are not an acceptable JPEG, PNG or WebP
     */
    public function process(string $bytes, ImagePolicy $policy): ProcessedImage;
}

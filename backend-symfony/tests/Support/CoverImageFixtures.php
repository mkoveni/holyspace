<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** The portal's multipart cover-image upload, for API tests (bytes come from `CoverImageBytes`). */
trait CoverImageFixtures
{
    /** Posts `$bytes` as the multipart `image` part with `X-Image-Version` (omitted when null). */
    protected function uploadCover(string $uri, ?string $token, ?int $version, string $bytes, string $declaredType = 'image/png'): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($path, $bytes);
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        if (null !== $version) {
            $server['HTTP_X_IMAGE_VERSION'] = (string) $version;
        }
        $this->client->request('POST', $uri, files: ['image' => new UploadedFile($path, 'cover.png', $declaredType, test: true)], server: $server);
    }

    protected function removeCover(string $uri, ?string $token, int $version): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_IMAGE_VERSION' => (string) $version];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request('DELETE', $uri, server: $server);
    }

    /** Fetches a member-facing cover URL exactly as a native image loader would: no session, no token. */
    protected function fetchPublicCover(string $url): void
    {
        $this->client->request('GET', $url);
    }
}

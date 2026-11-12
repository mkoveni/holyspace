<?php

declare(strict_types=1);

namespace App\Events\Infrastructure;

use App\Events\Application\Contract\VisitClaim;
use App\Events\Application\Contract\VisitTokens;
use Symfony\Component\Uid\Uuid;

/**
 * One opaque URL-safe string: base64url of the occurrence id (16 bytes), the link generation (4 bytes) and an
 * HMAC-SHA256 signature over both, keyed by the application secret. It has no dots, because dev servers and
 * proxies treat a dotted last path segment as a file name. Nothing is stored, so staff can show the same QR code
 * again; rotating the generation invalidates the old one.
 */
final readonly class HmacVisitTokens implements VisitTokens
{
    private const int SUBJECT_BYTES = 20;

    public function __construct(private string $secret)
    {
    }

    public function issue(string $occurrenceId, int $generation): string
    {
        $subject = Uuid::fromString($occurrenceId)->toBinary().pack('N', $generation);

        return rtrim(strtr(base64_encode($subject.$this->signature($subject)), '+/', '-_'), '=');
    }

    public function verify(string $token): ?VisitClaim
    {
        if (1 !== preg_match('/\A[A-Za-z0-9_-]{70}\z/', $token)) {
            return null;
        }
        $bytes = base64_decode(strtr($token, '-_', '+/'), true);
        if (false === $bytes || self::SUBJECT_BYTES + 32 !== \strlen($bytes)) {
            return null;
        }
        $subject = substr($bytes, 0, self::SUBJECT_BYTES);
        if (!hash_equals($this->signature($subject), substr($bytes, self::SUBJECT_BYTES))) {
            return null;
        }
        $generation = unpack('N', substr($subject, 16))[1] ?? 0;
        if (!\is_int($generation) || $generation < 1) {
            return null;
        }

        return new VisitClaim(Uuid::fromBinary(substr($subject, 0, 16))->toRfc4122(), $generation);
    }

    private function signature(string $subject): string
    {
        return hash_hmac('sha256', 'event-visit-link|'.$subject, $this->secret, true);
    }
}

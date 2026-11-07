<?php

declare(strict_types=1);

namespace App\Tests\Communications\Infrastructure;

use App\Communications\Domain\CommunicationException;
use App\Communications\Infrastructure\Push\AesPushTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The stored format must stay readable by the Java backend and vice versa: `base64(nonce):base64(ciphertext || tag)`
 * with additional data `holyspace-push-v1`. The vectors come from an independent AES-GCM implementation (Python
 * `cryptography`, fixed nonce 00..0b), not yet from Java itself; see remaining-work.md (A2 log).
 */
final class AesPushTokensTest extends TestCase
{
    private const string KEY = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';

    /** @return array<string, array{string, string}> */
    public static function vectors(): array
    {
        return [
            'ascii' => ['AAECAwQFBgcICQoL:aJ3LE7d9vNj4qQLWJaC6YYhTd+JPyCvvRarS19162OvWzlhBeIckJKR4k/lNjHwEaG4yxNakwwcDEQ==', 'ExponentPushToken[test_device_token_12345]'],
            'utf-8' => ['AAECAwQFBgcICQoL:aJ3LE4ltocT8sxrbH5QSrQC0oWKRKyv+TrXY29xA8dLvwhMolR8YsmUQ5mo1auY=', 'ExpoPushToken[é漢字_unicode]'],
        ];
    }

    #[DataProvider('vectors')]
    public function testDecryptsTheSharedFormat(string $stored, string $token): void
    {
        self::assertSame($token, new AesPushTokens(self::KEY)->decrypt($stored));
    }

    public function testEncryptionRoundTripsWithAFreshNonceAndNeverContainsTheToken(): void
    {
        $tokens = new AesPushTokens(self::KEY);
        $token = 'ExponentPushToken[test_device_token_12345]';
        $first = $tokens->encrypt($token);
        $second = $tokens->encrypt($token);

        self::assertNotSame($first, $second);
        self::assertStringNotContainsString($token, $first);
        self::assertMatchesRegularExpression('/\A[A-Za-z0-9+\/]{16}:[A-Za-z0-9+\/=]+\z/', $first);
        self::assertSame(12, strlen((string) base64_decode(explode(':', $first)[0], true)));
        self::assertSame(strlen($token) + 16, strlen((string) base64_decode(explode(':', $first)[1], true)), 'ciphertext followed by a 16-byte tag');
        self::assertSame($token, $tokens->decrypt($first));
    }

    public function testTamperingOrTheWrongKeyOrAdditionalDataIsRefused(): void
    {
        $stored = self::vectors()['ascii'][0];
        [$nonce, $sealed] = explode(':', $stored);
        $bytes = (string) base64_decode($sealed, true);
        $bytes[0] = chr(ord($bytes[0]) ^ 1);
        foreach ([
            [self::KEY, $nonce.':'.base64_encode($bytes)],
            [base64_encode(str_repeat('k', 32)), $stored],
            [self::KEY, 'no-separator'],
            [self::KEY, 'a:b:c'],
            [self::KEY, $nonce.':'.base64_encode('short')],
        ] as [$key, $value]) {
            try {
                new AesPushTokens($key)->decrypt($value);
                self::fail('Tampered or foreign ciphertext must be refused.');
            } catch (CommunicationException $expected) {
                self::assertSame('Push token decryption is unavailable.', $expected->getMessage());
            }
        }
    }

    public function testAMissingOrShortKeyIsUnavailable(): void
    {
        foreach (['', 'not base64!', base64_encode(str_repeat('k', 16))] as $key) {
            $tokens = new AesPushTokens($key);
            self::assertFalse($tokens->available());
            try {
                $tokens->encrypt('ExpoPushToken[abcdefghijkl]');
                self::fail('Encryption needs the configured key.');
            } catch (CommunicationException $expected) {
                self::assertSame('Configure the private push token encryption key.', $expected->getMessage());
            }
        }
        self::assertStringNotContainsString('MDEy', print_r(new AesPushTokens(self::KEY), true));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Payments\Domain;

use App\Payments\Domain\HttpsOrigin;
use App\Payments\Domain\JavaBase64;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\ProviderCredentials;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Port of Java ProviderCredentialsTest, plus the origin, base64 and order invariants it relies on. */
final class ProviderCredentialsTest extends TestCase
{
    private const string SIGNING_SECRET = 'whsec_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

    public function testAcceptsSigningSecretsAndKeepsEnvironmentsSeparate(): void
    {
        $credentials = new ProviderCredentials(null, null, null, 'sk_test_fixture', self::SIGNING_SECRET);
        self::assertTrue($credentials->complete('yoco', 'test'));
        $issue = (string) $credentials->validationIssue('yoco', 'live');
        self::assertStringContainsString('sk_live_', $issue);
        self::assertStringContainsString('live mode', $issue);
        self::assertStringNotContainsString('sk_test_fixture', $issue);
    }

    public function testExplainsPublicKeysAndMissingOrMalformedSecretsWithoutEchoingCredentials(): void
    {
        $public = (string) new ProviderCredentials(null, null, null, 'pk_test_private-fixture', self::SIGNING_SECRET)->validationIssue('yoco', 'test');
        self::assertStringContainsString('Public keys', $public);
        self::assertStringNotContainsString('pk_test_private-fixture', $public);
        self::assertStringContainsString('separate from the API key', (string) new ProviderCredentials(null, null, null, 'sk_test_fixture', '')->validationIssue('yoco', 'test'));
        foreach (['whsec_invalid-encoding', 'whsec_YQ==', 'whsec_'] as $secret) {
            $credentials = new ProviderCredentials(null, null, null, 'sk_test_fixture', $secret);
            self::assertFalse($credentials->complete('yoco', 'test'));
            self::assertStringContainsString('malformed', (string) $credentials->validationIssue('yoco', 'test'));
        }
    }

    public function testPayfastNeedsAnEightDigitMerchantKeyAndPassphrase(): void
    {
        self::assertTrue(new ProviderCredentials('10000100', 'key', 'pass')->complete('payfast', 'test'));
        self::assertSame('Enter the eight-digit Payfast merchant ID.', new ProviderCredentials('1000010', 'key', 'pass')->validationIssue('payfast', 'test'));
        self::assertSame('Enter the Payfast merchant key and passphrase.', new ProviderCredentials('10000100', 'key', ' ')->validationIssue('payfast', 'test'));
        $this->expectException(PaymentException::class);
        new ProviderCredentials("bad\nvalue");
    }

    public function testBlankReplacementsKeepSavedValuesAndRevisionsKeepOnlyTheProvidersSecrets(): void
    {
        $saved = new ProviderCredentials('10000100', 'key', 'pass', 'sk_test_x', 'whsec_x');
        $merged = $saved->replace(new ProviderCredentials(null, 'new-key'))->forProvider('payfast');
        self::assertSame(['10000100', 'new-key', 'pass', '', ''], [$merged->merchantId, $merged->merchantKey, $merged->passphrase, $merged->yocoKey, $merged->webhookSecret]);
        self::assertStringNotContainsString('new-key', print_r($merged, true));
    }

    public function testJavaBase64RulesAndOrigins(): void
    {
        self::assertSame('a', JavaBase64::decode('YQ'));
        self::assertNull(JavaBase64::decode('YQ='));
        self::assertNull(JavaBase64::decode('Y'));
        self::assertNull(JavaBase64::decode('YQ== '));
        self::assertSame('https://api.church.example', HttpsOrigin::public(' https://api.church.example/ '));
        self::assertSame('https://church.example:8443', HttpsOrigin::public('https://church.example:8443'));
        foreach (['', 'http://api.church.example', 'https://api.church.example/api', 'https://localhost', 'https://127.0.0.1',
            'https://user:password@api.church.example', 'https://api.church.example?x=1', 'https://api.church.example#x', 'HTTPS://church.example'] as $invalid) {
            try {
                HttpsOrigin::public($invalid);
                self::fail('Accepted '.$invalid);
            } catch (PaymentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testOrdersKeepTheirInvariants(): void
    {
        $start = static fn (int $amount, string $provider, bool $recurring): PaymentOrder => PaymentOrder::start('id', 'donor', 'fund', $amount, $provider, 'test', $recurring, str_repeat('h', 64), 'actor', new DateTimeImmutable(), null, false);
        self::assertSame(['PENDING', 'PENDING', 0], [($o = $start(500, 'payfast', true))->status, $o->subscriptionStatus, $o->version]);
        self::assertSame('NONE', $start(100_000_000, 'yoco', false)->subscriptionStatus);
        self::assertStringNotContainsString('encryptedToken', print_r($o, true));
        foreach ([[499, 'payfast', false], [100_000_001, 'payfast', false], [500, 'other', false], [500, 'yoco', true]] as [$amount, $provider, $recurring]) {
            try {
                $start($amount, $provider, $recurring);
                self::fail('Accepted an invalid order');
            } catch (PaymentException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame('Ab', PaymentOrder::text('  Ab ', 2, 'Label'));
        $this->expectExceptionMessage('Label is required (at most 2 characters).');
        PaymentOrder::text('😀x', 2, 'Label');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Payments\Infrastructure;

use App\Payments\Domain\PaymentException;
use App\Payments\Domain\ProviderCredentials;
use App\Payments\Infrastructure\Gateway\AesSubscriptionVault;
use App\Payments\Infrastructure\Gateway\EncryptedPaymentCredentials;
use PHPUnit\Framework\TestCase;

/** Ciphertexts written by Java's AesSubscriptionVault and EncryptedPaymentCredentials (PaymentVectors.java) must decrypt here. */
final class AesSubscriptionVaultTest extends TestCase
{
    private const string KEY = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';
    private const string TOKEN = '12345678-1234-1234-1234-123456789012';

    public function testDecryptsJavaCiphertexts(): void
    {
        $vault = new AesSubscriptionVault(self::KEY);
        self::assertSame(self::TOKEN, $vault->decrypt('pa1HbYNNAK7XXhUQ:f6Jy35XJ6VRwLrWhW4R41s1uxvgURvnGlde0IppsSvKtppzbHpaO41GwqcfUaR9G/2un5g=='));
        $credentials = new EncryptedPaymentCredentials($vault, self::KEY)->decrypt(
            'ymTizbl11bFSnOk0:9Ibxk+HHGBQcrS6sK6jCTJzMvs6EuEylPkioGojxKTx8sopj5uBOHJEyvjo8y01NTIr95YN18GH5hPFVWGmoXIewfYQuW6kQ/wc4dQpvZZMgwVchA3a45A6DonELYAujRcHGcsOtiWwkSTEkKq4PRJx45nFTSZcZS+vfpNljjI0W',
        );
        self::assertSame(['10000100', 'merchant-key', 'pass phrase', '', ''], [$credentials->merchantId, $credentials->merchantKey, $credentials->passphrase, $credentials->yocoKey, $credentials->webhookSecret]);
    }

    public function testWritesTheJavaFormatAndJson(): void
    {
        $vault = new AesSubscriptionVault(self::KEY);
        $encrypted = $vault->encrypt(self::TOKEN);
        self::assertMatchesRegularExpression('#\A[A-Za-z0-9+/]{16}:[A-Za-z0-9+/=]+\z#', $encrypted);
        self::assertStringNotContainsString(self::TOKEN, $encrypted);
        self::assertSame(self::TOKEN, $vault->decrypt($encrypted));
        self::assertNotSame($encrypted, $vault->encrypt(self::TOKEN));
        $sealed = new EncryptedPaymentCredentials($vault, self::KEY)->encrypt(new ProviderCredentials('10000100', 'merchant-key', 'pass phrase'));
        self::assertSame('{"merchantId":"10000100","merchantKey":"merchant-key","passphrase":"pass phrase","yocoKey":"","webhookSecret":""}', $vault->decrypt($sealed));
    }

    public function testTamperingAndWrongKeysFailClosed(): void
    {
        $vault = new AesSubscriptionVault(self::KEY);
        $encrypted = $vault->encrypt(self::TOKEN);
        foreach ([$encrypted.'broken', 'x:y:z', substr($encrypted, 0, -4).'AAA=', ''] as $bad) {
            try {
                $vault->decrypt($bad);
                self::fail('Tampered ciphertext decrypted');
            } catch (PaymentException $error) {
                self::assertSame('Payment token could not be decrypted. Check the configured key.', $error->getMessage());
            }
        }
        $this->expectExceptionMessage('Configure the private payment token encryption key.');
        new AesSubscriptionVault(base64_encode('short'))->encrypt(self::TOKEN);
    }

    public function testReadinessNeedsAThirtyTwoByteKey(): void
    {
        $vault = new AesSubscriptionVault('');
        self::assertFalse(new EncryptedPaymentCredentials($vault, '')->ready());
        self::assertFalse(new EncryptedPaymentCredentials($vault, 'not base64!')->ready());
        self::assertTrue(new EncryptedPaymentCredentials($vault, self::KEY)->ready());
    }
}

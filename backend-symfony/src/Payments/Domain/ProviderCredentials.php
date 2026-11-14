<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Shared\Domain\JavaString;
use SensitiveParameter;

/** Provider secrets (Java `ProviderCredentials`): blank means "not set", never logged or shown (`__debugInfo` redacts). */
final readonly class ProviderCredentials
{
    public string $yocoKey;
    public string $webhookSecret;

    public function __construct(
        #[SensitiveParameter] ?string $yocoKey = null,
        #[SensitiveParameter] ?string $webhookSecret = null,
    ) {
        $this->yocoKey = self::clean($yocoKey);
        $this->webhookSecret = self::clean($webhookSecret);
    }

    public static function none(): self
    {
        return new self();
    }

    private static function clean(#[SensitiveParameter] ?string $value): string
    {
        if (null === $value) {
            return '';
        }
        if (JavaString::length($value) > 1000 || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw PaymentException::invalid('Payment credentials contain unsupported characters or are too long.');
        }

        return JavaString::trim($value);
    }

    /** Blank replacements keep the saved value. */
    public function replace(self $input): self
    {
        return new self(
            '' === $input->yocoKey ? $this->yocoKey : $input->yocoKey,
            '' === $input->webhookSecret ? $this->webhookSecret : $input->webhookSecret,
        );
    }

    public function complete(string $mode): bool
    {
        return null === $this->validationIssue($mode);
    }

    /** Why these credentials cannot authenticate the provider, without echoing any of them. */
    public function validationIssue(string $mode): ?string
    {
        if (!str_starts_with($this->yocoKey, 'sk_'.$mode.'_')) {
            return 'Enter a Yoco secret API key for '.$mode.' mode (sk_'.$mode.'_...). Public keys cannot authenticate checkout requests.';
        }
        if (!str_starts_with($this->webhookSecret, 'whsec_')) {
            return 'Enter the Yoco webhook signing secret (whsec_...) returned when registering the callback URL. This is separate from the API key.';
        }
        if (\strlen((string) $this->signingKey()) >= 16) {
            return null;
        }

        return 'The Yoco webhook signing secret is malformed. Copy the complete whsec_... value returned when registering the callback URL, including any trailing = characters.';
    }

    /** The decoded Yoco signing key, or null when the secret is not valid base64 after `whsec_`. */
    public function signingKey(): ?string
    {
        return str_starts_with($this->webhookSecret, 'whsec_') ? JavaBase64::decode(substr($this->webhookSecret, 6)) : null;
    }

    /**
     * Saved values of at least four characters, which a free-text reason must not contain.
     *
     * @return list<string>
     */
    public function protectedValues(): array
    {
        return array_values(array_filter(
            [$this->yocoKey, $this->webhookSecret],
            static fn (string $value): bool => JavaString::length($value) >= 4,
        ));
    }

    /** @return array{redacted: true} */
    public function __debugInfo(): array
    {
        return ['redacted' => true];
    }
}

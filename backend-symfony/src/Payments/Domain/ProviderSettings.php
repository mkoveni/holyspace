<?php

declare(strict_types=1);

namespace App\Payments\Domain;

/** The versioned head of one provider's settings: active environment, new-checkout switch, monthly giving, managed flag. */
final readonly class ProviderSettings
{
    public function __construct(
        public string $provider,
        public string $mode,
        public bool $enabled,
        public bool $monthly,
        public bool $managed,
        public int $version,
    ) {
        self::validate($provider, $mode);
        if ($monthly) {
            throw PaymentException::invalid('Monthly giving is no longer offered.');
        }
    }

    public static function validate(?string $provider, ?string $mode): void
    {
        if (!\in_array($provider, PaymentOrder::PROVIDERS, true) || !\in_array($mode, PaymentOrder::MODES, true)) {
            throw PaymentException::invalid('Choose Yoco and a test or live environment.');
        }
    }

    public function expected(int $expected): void
    {
        if ($this->version !== $expected) {
            throw PaymentException::conflict('Payment settings changed. Reload before saving or checking the connection.');
        }
    }

    /** The next version of this head, with the given switches (the store saves it only over the current version). */
    public function next(string $mode, bool $enabled, bool $monthly, bool $managed): self
    {
        return new self($this->provider, $mode, $enabled, $monthly, $managed, $this->version + 1);
    }
}

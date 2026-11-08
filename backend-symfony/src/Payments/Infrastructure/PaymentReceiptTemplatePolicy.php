<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Communications\Application\Contract\TemplatePublicationPolicy;
use App\Payments\Application\PaymentReceiptPreview;

final readonly class PaymentReceiptTemplatePolicy implements TemplatePublicationPolicy
{
    public function violation(string $key, string $subject, string $html, array $variables): ?string
    {
        if (PaymentReceiptPreview::TEMPLATE !== $key) {
            return null;
        }
        if ([] !== array_diff(['name', 'amount', 'date', 'provider', 'reference'], $variables) || !str_contains(strtolower($html), 'not a tax certificate')) {
            return "Payment confirmations need name, amount, date, provider and reference variables and the wording 'not a tax certificate'.";
        }

        return null;
    }
}

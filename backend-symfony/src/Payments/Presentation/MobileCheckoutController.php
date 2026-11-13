<?php

declare(strict_types=1);

namespace App\Payments\Presentation;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Payments\Application\Command\CompleteMobileCheckout\CompleteMobileCheckoutCommand;
use App\Payments\Application\Command\PrepareMobileCheckout\PrepareMobileCheckoutCommand;
use App\Payments\Application\Command\PrepareMobileCheckout\PrepareMobileCheckoutHandler;
use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Domain\PaymentException;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The one-time native-to-browser checkout handoff. Preparing needs the donor's session; the landing page and its form
 * post are public (`security.yaml`) and authenticated only by the one-use token. The landing page is read-only; only
 * the POST consumes the token. Every response is no-store with a restrictive CSP.
 */
final readonly class MobileCheckoutController
{
    /**
     * Where the landing form may lead. Chrome applies `form-action` to the redirect that answers the post too, so a Yoco
     * checkout (a 303 to c.yoco.com) is blocked silently, after the one-use token was already spent, unless it is listed.
     * Payfast answers with a page of its own whose form carries its own policy.
     */
    private const string LANDING_FORM_ORIGINS = "'self' https://c.yoco.com";
    private const array PAYFAST_HOSTS = ['www.payfast.co.za', 'sandbox.payfast.co.za'];

    public function __construct(private CommandBus $commands, private AuthenticatedAccount $account, private PrepareMobileCheckoutHandler $prepare)
    {
    }

    #[Route('/api/my-giving/payments/{orderId}/mobile-checkout', methods: ['POST'])]
    public function prepare(#[PathUuid] string $orderId): JsonResponse
    {
        $response = new JsonResponse(PaymentJson::handoff($this->prepare->handle(new PrepareMobileCheckoutCommand($this->account->requireAccount(), $orderId))));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/api/mobile-checkouts/{token}', methods: ['GET'])]
    public function landing(string $token): Response
    {
        return self::page("<h1>Continue your gift.</h1><p>Your payment is handled securely by the provider. After payment, close this browser and refresh Divine Community Space.</p><form method='post'><button type='submit'>Continue to secure payment</button></form>", self::LANDING_FORM_ORIGINS);
    }

    #[Route('/api/mobile-checkouts/{token}', methods: ['POST'])]
    public function complete(string $token): Response
    {
        try {
            /** @var HostedCheckout $checkout */
            $checkout = $this->commands->dispatch(new CompleteMobileCheckoutCommand($token));
        } catch (PaymentException $error) {
            if (!$error->isMissing()) {
                throw $error;
            }
            $expired = self::page('<h1>Let’s reopen your checkout.</h1><p>This secure link expired or is no longer available. Return to Divine Community Space, refresh your payment and open a fresh checkout link for the same gift.</p>', "'none'");
            $expired->setStatusCode(410);

            return $expired;
        }

        return self::verified($checkout);
    }

    private static function verified(HostedCheckout $checkout): Response
    {
        $url = parse_url($checkout->url);
        $host = \is_array($url) ? ($url['host'] ?? '') : '';
        if (!\is_array($url) || 'https' !== ($url['scheme'] ?? null) || isset($url['user']) || isset($url['pass'])
            || (isset($url['port']) && 443 !== $url['port']) || 1 !== preg_match('#\A[!-~]+\z#', $checkout->url)) {
            throw PaymentException::unavailable('Provider checkout could not be verified.');
        }
        if ('c.yoco.com' === $host && [] === $checkout->fields) {
            $redirect = new Response(null, 303, ['Location' => $checkout->url, 'Referrer-Policy' => 'no-referrer']);
            $redirect->headers->set('Cache-Control', 'no-store');

            return $redirect;
        }
        if (!\in_array($host, self::PAYFAST_HOSTS, true) || '/eng/process' !== ($url['path'] ?? '')) {
            throw PaymentException::unavailable('Provider checkout could not be verified.');
        }
        $fields = '';
        foreach ($checkout->fields as $name => $value) {
            $fields .= "<input type='hidden' name='".self::escape((string) $name)."' value='".self::escape($value)."'>";
        }

        return self::page("<h1>Your gift is ready.</h1><p>Continue to Payfast to enter your payment details.</p><form method='post' action='"
            .self::escape($checkout->url)."'>".$fields."<button type='submit'>Continue to Payfast</button></form>", 'https://'.$host);
    }

    /** Spring's `HtmlUtils.htmlEscape`: & < > " and ' as entities. */
    private static function escape(string $value): string
    {
        return str_replace('&#039;', '&#39;', htmlspecialchars($value, \ENT_QUOTES | \ENT_HTML401, 'UTF-8'));
    }

    private static function page(string $body, string $formOrigin): Response
    {
        $html = "<!doctype html><html lang='en'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Divine Community Space · Secure giving</title><style>body{font:17px/1.6 system-ui;color:#183c34;background:#f6f8f4;margin:0;padding:48px 24px}main{max-width:480px;margin:8vh auto;background:white;padding:32px;border-radius:24px;border:1px solid #e1e8df}h1{line-height:1.15}button{background:#235846;color:white;border:0;border-radius:14px;padding:16px 22px;font:inherit;cursor:pointer;width:100%}small{letter-spacing:.18em;color:#718579}</style></head><body><main><small>DIVINE COMMUNITY SPACE</small>"
            .$body.'</main></body></html>';
        $response = new Response($html, 200, [
            'Content-Type' => 'text/html;charset=UTF-8',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action ".$formOrigin."; base-uri 'none'; frame-ancestors 'none'",
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}

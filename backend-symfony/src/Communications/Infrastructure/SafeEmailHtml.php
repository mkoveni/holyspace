<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure;

use App\Communications\Application\Contract\RichText;
use App\Communications\Domain\CommunicationException;
use Dom\HTMLDocument;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Email-safe rich text: headings, paragraphs, lists, emphasis and https/http/mailto links only. Scripts, styles,
 * images, event handlers and unsafe protocols are removed, and variables may stand in text but never in an
 * attribute (so one can never become a link address).
 */
final readonly class SafeEmailHtml implements RichText
{
    private const int MAX = 200000;

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = new HtmlSanitizerConfig()
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('blockquote')
            ->allowElement('a', ['href', 'title'])
            ->dropElement('script')
            ->dropElement('style')
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(self::MAX + 1);
        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        if (mb_strlen($html) > self::MAX) {
            throw CommunicationException::invalid('Enter an email body of at most 200000 characters.');
        }
        foreach (HTMLDocument::createFromString('<body>'.$html.'</body>', \LIBXML_NOERROR)->querySelectorAll('*') as $element) {
            foreach ($element->attributes as $attribute) {
                if (str_contains($attribute->value, '{{')) {
                    throw CommunicationException::invalid('Insert variables in text, never in link addresses or HTML attributes.');
                }
            }
        }

        $clean = $this->sanitizer->sanitizeFor('body', $html);
        $body = HTMLDocument::createFromString('<body>'.$clean.'</body>', \LIBXML_NOERROR)->body;
        $text = null === $body ? '' : (string) $body->textContent;
        if ('' === trim($text)) {
            throw CommunicationException::invalid('Add some text to the email body.');
        }

        return $clean;
    }
}

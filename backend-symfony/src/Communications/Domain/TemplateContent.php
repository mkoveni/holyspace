<?php

declare(strict_types=1);

namespace App\Communications\Domain;

/**
 * The subject, HTML body and declared variables of an email template. Variables are written {{name}}, must be
 * declared exactly as used, and are always inserted as literal text: never evaluated, never re-expanded, and
 * HTML-escaped in the body.
 */
final readonly class TemplateContent
{
    private const string TOKEN = '/\{\{\s*([a-zA-Z][a-zA-Z0-9_]{0,49})\s*\}\}/';
    private const string NAME = '/\A[a-zA-Z][a-zA-Z0-9_]{0,49}\z/';

    public string $subject;
    public string $html;

    /** @var list<string> */
    public array $variables;

    /** @param list<string> $variables */
    public function __construct(?string $subject, ?string $html, array $variables)
    {
        $this->subject = CommunicationRules::text($subject, 200);
        $this->html = CommunicationRules::text($html, 50000);
        if (str_contains($this->subject, "\r") || str_contains($this->subject, "\n")) {
            throw CommunicationException::invalid('The subject must be a single line.');
        }
        if (\count($variables) > 30 || \count(array_unique($variables)) !== \count($variables)) {
            throw CommunicationException::invalid('Declare up to 30 unique variable names using letters, digits and underscores.');
        }
        foreach ($variables as $name) {
            if (1 !== preg_match(self::NAME, $name)) {
                throw CommunicationException::invalid('Declare up to 30 unique variable names using letters, digits and underscores.');
            }
        }
        $this->variables = $variables;

        $source = $this->subject.$this->html;
        preg_match_all(self::TOKEN, $source, $matches);
        $used = array_values(array_unique($matches[1]));
        $declared = $this->variables;
        sort($used);
        sort($declared);
        if ($used !== $declared) {
            throw CommunicationException::invalid('Declared variables must exactly match the variables used in the subject and body.');
        }
        $remaining = preg_replace(self::TOKEN, '', $source) ?? '';
        if (str_contains($remaining, '{{') || str_contains($remaining, '}}')) {
            throw CommunicationException::invalid('Use variables in the form {{variableName}}.');
        }
    }

    /** @param array<string, string> $values */
    public function render(array $values): RenderedContent
    {
        $given = array_map('strval', array_keys($values));
        $declared = $this->variables;
        sort($given);
        sort($declared);
        if ($given !== $declared) {
            throw $this->badValues();
        }
        foreach ($values as $value) {
            if (mb_strlen($value) > 5000) {
                throw $this->badValues();
            }
        }

        $subject = $this->replace($this->subject, $values, false);
        if (mb_strlen($subject) > 200 || str_contains($subject, "\r") || str_contains($subject, "\n")) {
            throw CommunicationException::invalid('The rendered subject must be a single line of at most 200 characters.');
        }
        $html = $this->replace($this->html, $values, true);
        if (mb_strlen($html) > 200000) {
            throw CommunicationException::invalid('Rendered message is too large.');
        }

        return new RenderedContent($subject, $html);
    }

    private function badValues(): CommunicationException
    {
        return CommunicationException::invalid('Supply every declared variable exactly once, with at most 5000 characters per value.');
    }

    /** @param array<string, string> $values */
    private function replace(string $source, array $values, bool $html): string
    {
        return preg_replace_callback(self::TOKEN, static function (array $match) use ($values, $html): string {
            $value = $values[$match[1]];

            return $html ? str_replace(['&', '<', '>', '"', "'"], ['&amp;', '&lt;', '&gt;', '&quot;', '&#39;'], $value) : $value;
        }, $source) ?? '';
    }
}

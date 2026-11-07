<?php

declare(strict_types=1);

namespace App\Tests\Communications\Domain;

use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\TemplateContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemplateContentTest extends TestCase
{
    public function testVariablesAreLiteralTextAndNeverRecursivelyEvaluated(): void
    {
        $content = new TemplateContent('Hello {{name}}', '<p>{{name}}</p>', ['name']);

        $rendered = $content->render(['name' => '<img src=x onerror=alert(1)> & {{other}} $1 \\1']);

        self::assertStringContainsString('&lt;img', $rendered->html);
        self::assertStringContainsString('&amp;', $rendered->html);
        self::assertStringContainsString('{{other}}', $rendered->html);
        self::assertStringContainsString('$1 \\1', $rendered->html);
        self::assertStringNotContainsString('<img', $rendered->html);
        self::assertStringContainsString('<img', $rendered->subject, 'the subject is plain text and keeps the value as typed');
    }

    public function testTheSubjectIsPlainTextButTheBodyIsEscaped(): void
    {
        $rendered = new TemplateContent('Hi {{name}}', '<p>{{name}}</p>', ['name'])->render(['name' => 'Tom & "Jerry"']);

        self::assertSame('Hi Tom & "Jerry"', $rendered->subject);
        self::assertSame('<p>Tom &amp; &quot;Jerry&quot;</p>', $rendered->html);
        self::assertSame('<p>it&#39;s</p>', new TemplateContent('S', '<p>{{a}}</p>', ['a'])->render(['a' => "it's"])->html);
    }

    /** @return iterable<string, array{string, string, list<string>}> */
    public static function invalidContent(): iterable
    {
        yield 'undeclared variable' => ['Hello', '<p>{{missing}}</p>', []];
        yield 'declared but unused' => ['Hello', '<p>Hi</p>', ['name']];
        yield 'dotted name' => ['Hello', '<p>{{person.name}}</p>', []];
        yield 'stray braces' => ['Hello {{', '<p>Hi</p>', []];
        yield 'closing braces only' => ['Hello', '<p>}} Hi</p>', []];
        yield 'newline in subject' => ["Hello\nthere", '<p>Hi</p>', []];
        yield 'blank subject' => ['   ', '<p>Hi</p>', []];
        yield 'blank body' => ['Hello', ' ', []];
        yield 'subject too long' => [str_repeat('s', 201), '<p>Hi</p>', []];
        yield 'bad variable name' => ['Hello {{1st}}', '<p>x</p>', ['1st']];
        yield 'duplicate declared' => ['Hello {{a}}', '<p>x</p>', ['a', 'a']];
        yield 'too many variables' => ['Hello', '<p>'.implode('', array_map(static fn (int $i): string => '{{v'.$i.'}}', range(1, 31))).'</p>', array_map(static fn (int $i): string => 'v'.$i, range(1, 31))];
    }

    /** @param list<string> $variables */
    #[DataProvider('invalidContent')]
    public function testInvalidContentIsRejected(string $subject, string $html, array $variables): void
    {
        $this->expectException(CommunicationException::class);

        new TemplateContent($subject, $html, $variables);
    }

    public function testSpacesInsideBracesAreAllowedAndCountAsTheSameVariable(): void
    {
        $content = new TemplateContent('Hi {{ name }}', '<p>{{name}} and {{ name  }}</p>', ['name']);

        self::assertSame('<p>Ann and Ann</p>', $content->render(['name' => 'Ann'])->html);
    }

    public function testMissingAndUnknownValuesAreRejectedAtRender(): void
    {
        $content = new TemplateContent('Hi {{name}}', '<p>{{name}}</p>', ['name']);

        foreach ([[], ['name' => 'Alex', 'other' => 'value'], ['other' => 'x'], ['name' => str_repeat('x', 5001)]] as $values) {
            try {
                $content->render($values);
                self::fail('Expected a rejection.');
            } catch (CommunicationException $error) {
                self::assertSame('Supply every declared variable exactly once, with at most 5000 characters per value.', $error->getMessage());
            }
        }
    }

    public function testSubjectHeaderInjectionIsRejectedAfterRendering(): void
    {
        $content = new TemplateContent('Hi {{name}}', '<p>Hello</p>', ['name']);

        foreach (["Alex\r\nBcc: other@example.org", "Alex\nx", "Alex\ry"] as $name) {
            try {
                $content->render(['name' => $name]);
                self::fail('Expected a rejection.');
            } catch (CommunicationException $error) {
                self::assertSame('The rendered subject must be a single line of at most 200 characters.', $error->getMessage());
            }
        }
    }

    public function testTheRenderedSubjectMayNotExceed200Characters(): void
    {
        $this->expectException(CommunicationException::class);

        new TemplateContent('{{a}}', '<p>x</p>', ['a'])->render(['a' => str_repeat('s', 201)]);
    }

    public function testTheRenderedBodyHasAnUpperBound(): void
    {
        $body = '<p>'.str_repeat('{{a}}', 41).'</p>';
        $content = new TemplateContent('S', $body, ['a']);

        $this->expectExceptionMessage('Rendered message is too large.');
        $content->render(['a' => str_repeat('&', 1000)]);
    }
}

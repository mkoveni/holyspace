<?php

declare(strict_types=1);

namespace App\Tests\Communications\Infrastructure;

use App\Communications\Domain\CommunicationException;
use App\Communications\Infrastructure\SafeEmailHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SafeEmailHtmlTest extends TestCase
{
    private SafeEmailHtml $html;

    protected function setUp(): void
    {
        $this->html = new SafeEmailHtml();
    }

    public function testScriptsImagesEventHandlersStylesAndUnsafeProtocolsAreRemoved(): void
    {
        $safe = $this->html->sanitize("<h2>Hello</h2><p onclick='alert(1)' style='color:red'>{{name}}<script>alert(1)</script><img src='https://tracker.test/x'><a href='javascript:alert(1)'>Bad</a><a href='https://church.test'>Visit</a></p>");

        foreach (['<h2>Hello</h2>', '{{name}}', 'https://church.test', 'noopener noreferrer'] as $kept) {
            self::assertStringContainsString($kept, $safe);
        }
        foreach (['script', 'onclick', 'style=', '<img', 'javascript:', 'tracker.test', 'alert(1)'] as $removed) {
            self::assertStringNotContainsString($removed, $safe);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function variableInAttribute(): iterable
    {
        yield 'link address' => ["<p><a href='{{url}}'>Visit</a></p>"];
        yield 'title' => ["<p><a href='https://x.test' title='{{name}}'>Visit</a></p>"];
        yield 'unknown attribute' => ["<p data-x='{{name}}'>Hi</p>"];
    }

    #[DataProvider('variableInAttribute')]
    public function testVariablesCannotBecomeLinkAddressesOrAttributeValues(string $html): void
    {
        $this->expectException(CommunicationException::class);
        $this->expectExceptionMessage('Insert variables in text, never in link addresses or HTML attributes.');

        $this->html->sanitize($html);
    }

    public function testOnlyTheDocumentedFormattingSurvives(): void
    {
        $safe = $this->html->sanitize('<p>a<br>b <strong>c</strong> <em>d</em> <u>e</u> <s>f</s></p><ul><li>g</li></ul><ol><li>h</li></ol><h3>i</h3><blockquote>j</blockquote><table><tr><td>k</td></tr></table><iframe src="https://x.test"></iframe><form action="https://x.test"><input name="n"></form>');

        foreach (['<strong>c</strong>', '<em>d</em>', '<u>e</u>', '<s>f</s>', '<ul><li>g</li></ul>', '<ol><li>h</li></ol>', '<h3>i</h3>', '<blockquote>j</blockquote>'] as $kept) {
            self::assertStringContainsString($kept, $safe);
        }
        foreach (['<table', '<td', '<iframe', '<form', '<input'] as $removed) {
            self::assertStringNotContainsString($removed, $safe);
        }
    }

    public function testLinksKeepOnlySafeSchemesAndAlwaysGetNoopener(): void
    {
        $safe = $this->html->sanitize('<p><a href="http://a.test">a</a> <a href="mailto:x@y.test">b</a> <a href="/relative">c</a> <a href="data:text/html,x">d</a> <a href="  JaVaScRiPt:alert(1)">e</a></p>');

        self::assertStringContainsString('href="http://a.test"', $safe);
        self::assertStringContainsString('href="mailto:x&#64;y.test"', $safe, 'the at sign is entity-encoded, which browsers and mail clients decode');
        foreach (['/relative', 'data:', 'javascript', 'JaVaScRiPt'] as $removed) {
            self::assertStringNotContainsStringIgnoringCase($removed, $safe);
        }
        self::assertSame(5, substr_count($safe, 'rel="noopener noreferrer"'), 'even an anchor whose address was removed carries it');
    }

    public function testAForgedRelOrTargetCannotWeakenLinks(): void
    {
        $safe = $this->html->sanitize('<p><a href="https://a.test" rel="opener" target="_blank">a</a></p>');

        self::assertStringContainsString('rel="noopener noreferrer"', $safe);
        self::assertStringNotContainsString('opener"', str_replace('noopener', '', $safe));
        self::assertStringNotContainsString('target', $safe);
    }

    public function testAnEmptyOrAllRemovedBodyIsRejected(): void
    {
        foreach (['', '   ', '<p></p>', '<script>alert(1)</script>', '<img src="https://x.test/a.png">'] as $empty) {
            try {
                $this->html->sanitize($empty);
                self::fail('Expected a rejection for '.$empty);
            } catch (CommunicationException $error) {
                self::assertSame('Add some text to the email body.', $error->getMessage());
            }
        }
    }

    public function testOversizedBodiesAreRejected(): void
    {
        $this->expectExceptionMessage('Enter an email body of at most 200000 characters.');

        $this->html->sanitize('<p>'.str_repeat('a', 200001).'</p>');
    }

    public function testTextIsEscapedNotInterpreted(): void
    {
        $safe = $this->html->sanitize('<p>1 &lt; 2 &amp; 3 &gt; 2 {{name}}</p>');

        self::assertStringContainsString('1 &lt; 2 &amp; 3 &gt; 2 {{name}}', $safe);
    }
}

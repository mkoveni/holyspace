<?php

declare(strict_types=1);

namespace App\Tests\Communications\Domain;

use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\CommunicationRules;
use App\Communications\Domain\EmailTemplate;
use App\Communications\Domain\TemplateContent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTemplateTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';

    private function content(): TemplateContent
    {
        return new TemplateContent('Subject', '<p>Body</p>', []);
    }

    private function draft(): EmailTemplate
    {
        return EmailTemplate::draft(self::ID, 'children.welcome', 'Welcome', $this->content(), self::ACTOR, new DateTimeImmutable('2026-06-01T10:00:00Z'));
    }

    /** @return iterable<string, array{string}> */
    public static function badKeys(): iterable
    {
        yield 'too short' => ['ab'];
        yield 'uppercase' => ['Children.welcome'];
        yield 'leading digit' => ['1abc'];
        yield 'space' => ['children welcome'];
        yield 'underscore' => ['children_welcome'];
        yield 'too long' => ['a'.str_repeat('b', 100)];
    }

    #[DataProvider('badKeys')]
    public function testKeysAreLowercaseDotsAndHyphens(string $key): void
    {
        $this->expectException(CommunicationException::class);

        EmailTemplate::draft(self::ID, $key, 'Name', $this->content(), self::ACTOR, new DateTimeImmutable());
    }

    public function testADraftIsVersionZeroAndUnpublished(): void
    {
        $draft = $this->draft();

        self::assertSame([EmailTemplate::DRAFT, 0, null], [$draft->status, $draft->version, $draft->publishedVersion]);
    }

    public function testPublishingAdvancesTheVersionAndPointsAtIt(): void
    {
        $published = $this->draft()->publish(self::ACTOR, new DateTimeImmutable('2026-06-02T10:00:00Z'));

        self::assertSame([EmailTemplate::PUBLISHED, 1, 1], [$published->status, $published->version, $published->publishedVersion]);
    }

    public function testEditingAfterPublishingMakesADraftAndKeepsThePublishedRevision(): void
    {
        $published = $this->draft()->publish(self::ACTOR, new DateTimeImmutable());

        $edited = $published->edit('Welcome 2', $this->content(), self::ACTOR, new DateTimeImmutable());

        self::assertSame([EmailTemplate::DRAFT, 2, 1, 'Welcome 2'], [$edited->status, $edited->version, $edited->publishedVersion, $edited->name]);
        self::assertSame([EmailTemplate::PUBLISHED, 3, 3], [$edited->publish(self::ACTOR, new DateTimeImmutable())->status, $edited->publish(self::ACTOR, new DateTimeImmutable())->version, $edited->publish(self::ACTOR, new DateTimeImmutable())->publishedVersion]);
    }

    public function testAPublishedRevisionCannotBePublishedAgainAndAnArchivedTemplateIsFrozen(): void
    {
        $published = $this->draft()->publish(self::ACTOR, new DateTimeImmutable());
        $archived = $published->archive(self::ACTOR, new DateTimeImmutable());

        foreach ([
            ['This revision is already published.', fn () => $published->publish(self::ACTOR, new DateTimeImmutable())],
            ['Archived templates cannot be edited.', fn () => $archived->edit('x', $this->content(), self::ACTOR, new DateTimeImmutable())],
            ['Archived templates cannot be published.', fn () => $archived->publish(self::ACTOR, new DateTimeImmutable())],
        ] as [$message, $action]) {
            try {
                $action();
                self::fail('Expected a rejection.');
            } catch (CommunicationException $error) {
                self::assertSame($message, $error->getMessage());
            }
        }
        self::assertSame([EmailTemplate::ARCHIVED, 2, 1], [$archived->status, $archived->version, $archived->publishedVersion]);
    }

    public function testTextAndVersionRules(): void
    {
        self::assertSame('abc', CommunicationRules::text('  abc  ', 10));
        foreach ([null, '', '   ', 'abcdef'] as $bad) {
            try {
                CommunicationRules::text($bad, 5);
                self::fail('Expected a rejection.');
            } catch (CommunicationException $error) {
                self::assertSame('Enter text of at most 5 characters.', $error->getMessage());
            }
        }
        $this->expectExceptionMessage('This record changed. Refresh before saving.');
        CommunicationRules::version(2, 1);
    }
}

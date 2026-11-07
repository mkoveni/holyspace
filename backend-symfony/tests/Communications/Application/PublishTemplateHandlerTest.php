<?php

declare(strict_types=1);

namespace App\Tests\Communications\Application;

use App\Communications\Application\Command\PublishTemplate\PublishTemplateCommand;
use App\Communications\Application\Command\PublishTemplate\PublishTemplateHandler;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\EmailTemplate;
use App\Communications\Domain\TemplateContent;
use App\IdentityAccess\Domain\IdentityException;
use App\Tests\Communications\Support\FixedPolicy;
use App\Tests\Communications\Support\InMemoryTemplates;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** A source context can veto a template it depends on: nothing is saved, no revision exists, no audit is written. */
final class PublishTemplateHandlerTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';

    private InMemoryTemplates $templates;

    protected function setUp(): void
    {
        $this->templates = new InMemoryTemplates(EmailTemplate::draft(self::ID, 'children.session-reminder', 'Reminder', new TemplateContent('Reminder', '<p>No placeholder</p>', []), self::ACTOR, new DateTimeImmutable('2026-06-01T09:00:00Z')));
    }

    /**
     * @param list<FixedPolicy> $policies
     * @param list<string>      $permissions
     */
    private function handler(array $policies, array $permissions = ['communications.templates.manage']): PublishTemplateHandler
    {
        return new PublishTemplateHandler(new AllowedPermissions($permissions), $this->templates, $this->templates, new MockClock('2026-06-01 10:00:00 UTC'), $policies);
    }

    public function testEveryPolicyIsAskedAboutTheExactContentBeforeAnythingIsSaved(): void
    {
        $policy = new FixedPolicy();

        $id = $this->handler([$policy])->handle(new PublishTemplateCommand(self::ACTOR, self::ID, 0));

        self::assertSame(self::ID, $id);
        self::assertSame(['children.session-reminder', 'Reminder', '<p>No placeholder</p>', []], $policy->seen);
        self::assertSame(['save', 'publish', 'audit:template.publishtemplate'], $this->templates->log);
        self::assertSame(['PUBLISHED', 1], [$this->templates->template?->status, $this->templates->template?->publishedVersion]);
    }

    public function testAPolicyViolationIsAnInvalidRequestAndNothingIsStored(): void
    {
        try {
            $this->handler([new FixedPolicy(), new FixedPolicy('Children templates must keep the message variable.')])->handle(new PublishTemplateCommand(self::ACTOR, self::ID, 0));
            self::fail('Expected a rejection.');
        } catch (CommunicationException $error) {
            self::assertSame('Children templates must keep the message variable.', $error->getMessage());
        }

        self::assertSame([], $this->templates->log);
        self::assertSame('DRAFT', $this->templates->template?->status);
    }

    public function testAnUnknownTemplateAndAStaleVersionAreRefusedBeforeThePolicies(): void
    {
        $policy = new FixedPolicy('never asked');
        $this->templates->template = null;
        try {
            $this->handler([$policy])->handle(new PublishTemplateCommand(self::ACTOR, self::ID, 0));
            self::fail('Expected a rejection.');
        } catch (CommunicationException $error) {
            self::assertSame('Template was not found.', $error->getMessage());
        }
        $this->setUp();
        try {
            $this->handler([$policy])->handle(new PublishTemplateCommand(self::ACTOR, self::ID, 4));
            self::fail('Expected a rejection.');
        } catch (CommunicationException $error) {
            self::assertSame('This record changed. Refresh before saving.', $error->getMessage());
        }

        self::assertNull($policy->seen);
    }

    public function testPublishingNeedsThePermission(): void
    {
        $this->expectException(IdentityException::class);

        $this->handler([], ['communications.templates.read'])->handle(new PublishTemplateCommand(self::ACTOR, self::ID, 0));
    }
}

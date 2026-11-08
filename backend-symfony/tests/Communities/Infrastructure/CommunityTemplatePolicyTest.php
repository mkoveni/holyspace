<?php

declare(strict_types=1);

namespace App\Tests\Communities\Infrastructure;

use App\Communities\Infrastructure\CommunityTemplatePolicy;
use PHPUnit\Framework\TestCase;

final class CommunityTemplatePolicyTest extends TestCase
{
    private const string VIOLATION = 'Member notices require name, community and message variables, with message in the body and community in the rendered notice.';

    public function testTheSeededNoticeIsAccepted(): void
    {
        self::assertNull((new CommunityTemplatePolicy())->violation('communities.notice', 'A message from {{community}}', '<h2>Hello {{name}}</h2><p>{{message}}</p>', ['name', 'community', 'message']));
    }

    public function testOtherTemplatesAreNotJudged(): void
    {
        self::assertNull((new CommunityTemplatePolicy())->violation('events.reminder', '', '', []));
    }

    public function testVariablesMustBeExactlyTheThreeAndPlaced(): void
    {
        $policy = new CommunityTemplatePolicy();

        self::assertSame(self::VIOLATION, $policy->violation('communities.notice', '{{community}}', '{{message}}', ['name', 'community']));
        self::assertSame(self::VIOLATION, $policy->violation('communities.notice', '{{community}}', '{{message}}', ['name', 'community', 'message', 'extra']));
        self::assertSame(self::VIOLATION, $policy->violation('communities.notice', '{{community}} {{message}}', '<p>{{name}}</p>', ['name', 'community', 'message']));
        self::assertSame(self::VIOLATION, $policy->violation('communities.notice', 'Hello', '<p>{{message}}</p>', ['name', 'community', 'message']));
        self::assertNull($policy->violation('communities.notice', 'Hello', '<p>{{community}}: {{message}}</p>', ['message', 'name', 'community']));
    }
}

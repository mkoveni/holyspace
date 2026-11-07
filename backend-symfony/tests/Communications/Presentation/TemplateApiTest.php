<?php

declare(strict_types=1);

namespace App\Tests\Communications\Presentation;

use App\Communications\Application\PublishedEmailTemplates;
use App\Communications\Domain\CommunicationException;
use App\Communications\Infrastructure\Persistence\DbalTemplateStore;
use App\Communications\Infrastructure\SafeEmailHtml;
use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

final class TemplateApiTest extends IdentityApiTestCase
{
    private string $admin;
    private string $reader;
    private string $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
        $this->accountWith('reader', ['communications.templates.read']);
        $this->reader = $this->login('reader');
    }

    /** @return array<string, mixed> */
    private function draft(string $key = 'church.welcome', string $html = '<p>Welcome {{name}}</p>'): array
    {
        return ['key' => $key, 'name' => 'Welcome', 'subject' => 'Welcome {{name}}', 'html' => $html, 'variables' => ['name']];
    }

    private function create(string $key = 'church.welcome'): string
    {
        $this->call('POST', '/api/communications/templates', $this->admin, $this->draft($key));
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function publish(string $id, int $version): void
    {
        $this->call('POST', "/api/communications/templates/$id/publish", $this->admin, ['expectedVersion' => $version]);
        self::assertSame(200, $this->code(), $this->message());
    }

    public function testTheTwoChildrenTemplatesAreSeededAndPublished(): void
    {
        $this->call('GET', '/api/communications/templates?search=children', $this->reader);

        self::assertSame(200, $this->code());
        $keys = array_column($this->json()['templates'], 'key');
        sort($keys);
        self::assertSame(['children.consent-renewal', 'children.session-reminder'], $keys);
        self::assertSame(2, $this->json()['total']);
        self::assertSame(['PUBLISHED', 0, ['message']], [$this->json()['templates'][0]['status'], $this->json()['templates'][0]['publishedVersion'], $this->json()['templates'][0]['variables']]);
    }

    public function testCreatingADraftSanitizesAndAuditsItAndReadsBack(): void
    {
        $this->call('POST', '/api/communications/templates', $this->admin, $this->draft(html: "<p onclick='x()'>Welcome {{name}}<script>alert(1)</script><img src='https://t.test/a'></p>"));
        self::assertSame(201, $this->code(), $this->message());
        $id = (string) $this->json()['id'];

        $this->call('GET', "/api/communications/templates/$id", $this->reader);
        $view = $this->json();
        self::assertSame(['church.welcome', 'DRAFT', 0, null, ['name'], '<p>Welcome {{name}}</p>'], [$view['key'], $view['status'], $view['version'], $view['publishedVersion'], $view['variables'], $view['html']]);
        self::assertMatchesRegularExpression('/Z\z/', $view['updatedAt']);
        self::assertSame(1, $this->rows('communication_audit', "action = 'template.createtemplate' AND target_id = '$id' AND actor_id = '{$this->adminId}'"));
    }

    public function testCreationRulesAreEnforced(): void
    {
        $this->create('church.welcome');
        foreach ([
            [$this->draft('church.welcome'), 409, 'A template with this key already exists.'],
            [$this->draft('Bad Key'), 400, 'Use a template key of 3–100 lowercase letters, numbers, dots or hyphens.'],
            [['variables' => []] + $this->draft('church.other'), 400, 'Declared variables must exactly match the variables used in the subject and body.'],
            [['variables' => ['name', 'extra']] + $this->draft('church.other'), 400, 'Declared variables must exactly match the variables used in the subject and body.'],
            [$this->draft('church.other', '<p><a href="{{name}}">Go</a></p>'), 400, 'Insert variables in text, never in link addresses or HTML attributes.'],
            [$this->draft('church.other', '<script>x</script>'), 400, 'Add some text to the email body.'],
            [['subject' => "One\nTwo"] + $this->draft('church.other'), 400, 'The subject must be a single line.'],
            [['name' => ''] + $this->draft('church.other'), 400, null],
            [['key' => null] + $this->draft('church.other'), 400, null],
            [['variables' => 'name'] + $this->draft('church.other'), 400, null],
            [['variables' => array_map(static fn (int $i): string => 'v'.$i, range(1, 31))] + $this->draft('church.other'), 400, null],
            [['subject' => str_repeat('s', 201)] + $this->draft('church.other'), 400, null],
        ] as [$body, $status, $message]) {
            $this->call('POST', '/api/communications/templates', $this->admin, $body);
            self::assertSame($status, $this->code(), json_encode($body, JSON_THROW_ON_ERROR));
            if (null !== $message) {
                self::assertSame($message, $this->message());
            }
        }
        self::assertSame(1, $this->rows('communication_templates', "template_key LIKE 'church.%'"));
    }

    public function testEveryRouteNeedsItsPermission(): void
    {
        $id = $this->create();
        $this->accountWith('manager', ['communications.templates.manage']);
        $manager = $this->login('manager');
        $this->accountWith('nobody', []);
        $nobody = $this->login('nobody');
        $writes = [
            ['POST', '/api/communications/templates', $this->draft('church.other')],
            ['PUT', "/api/communications/templates/$id", ['expectedVersion' => 0] + $this->draft()],
            ['POST', "/api/communications/templates/$id/publish", ['expectedVersion' => 0]],
            ['POST', "/api/communications/templates/$id/archive", ['expectedVersion' => 0]],
        ];
        $reads = [
            ['GET', '/api/communications/templates', null],
            ['GET', "/api/communications/templates/$id", null],
            ['GET', "/api/communications/templates/$id/revisions", null],
            ['POST', '/api/communications/templates/preview', ['subject' => 'S', 'html' => '<p>x</p>', 'variables' => [], 'values' => []]],
        ];
        foreach ([...$writes, ...$reads] as [$method, $path, $body]) {
            $this->call($method, $path, null, $body);
            self::assertSame(401, $this->code(), "anonymous $method $path");
            $this->call($method, $path, $nobody, $body);
            self::assertSame(403, $this->code(), "nobody $method $path");
        }
        foreach ($writes as [$method, $path, $body]) {
            $this->call($method, $path, $this->reader, $body);
            self::assertSame(403, $this->code(), "reader $method $path");
        }
        foreach ($reads as [$method, $path, $body]) {
            $this->call($method, $path, $manager, $body);
            self::assertNotSame(403, $this->code(), "manage implies read: $method $path");
        }
        self::assertSame(1, $this->rows('communication_templates', "template_key = 'church.welcome'"));
        self::assertSame(0, $this->rows('communication_templates', "template_key = 'church.other'"));
    }

    public function testEditingMakesADraftWhilePublishedRevisionsStayImmutable(): void
    {
        $id = $this->create();
        $this->publish($id, 0);
        $this->call('GET', "/api/communications/templates/$id", $this->reader);
        self::assertSame(['PUBLISHED', 1, 1], [$this->json()['status'], $this->json()['version'], $this->json()['publishedVersion']]);

        $edit = ['expectedVersion' => 1, 'name' => 'Welcome v2', 'subject' => 'Hello {{name}}', 'html' => '<p>Hello {{name}}</p>', 'variables' => ['name']];
        $this->call('PUT', "/api/communications/templates/$id", $this->admin, ['expectedVersion' => 7] + $edit);
        self::assertSame(409, $this->code());
        $this->call('PUT', "/api/communications/templates/$id", $this->admin, $edit);
        self::assertSame([200, $id], [$this->code(), $this->json()['id']]);

        $this->call('GET', "/api/communications/templates/$id", $this->reader);
        self::assertSame(['DRAFT', 2, 1, 'Welcome v2', 'Hello {{name}}'], [$this->json()['status'], $this->json()['version'], $this->json()['publishedVersion'], $this->json()['name'], $this->json()['subject']]);

        $rendered = $this->rendering()->render('church.welcome', ['name' => 'Ann']);
        self::assertSame(['Welcome Ann', 1, '<p>Welcome Ann</p>'], [$rendered->subject, $rendered->version, $rendered->html], 'recipients still get the published revision');

        $this->publish($id, 2);
        self::assertSame(['Hello Ann', 3], [$this->rendering()->render('church.welcome', ['name' => 'Ann'])->subject, $this->rendering()->render('church.welcome', ['name' => 'Ann'])->version]);
        $this->call('GET', "/api/communications/templates/$id/revisions", $this->reader);
        self::assertSame([3, 1], array_column($this->json(), 'version'), 'newest first, one per publication');
        self::assertSame(['Welcome {{name}}', ['name']], [$this->json()[1]['subject'], $this->json()[1]['variables']]);
    }

    public function testPublishingTwiceOrAnArchivedTemplateIsRefused(): void
    {
        $id = $this->create();
        $this->publish($id, 0);
        $this->call('POST', "/api/communications/templates/$id/publish", $this->admin, ['expectedVersion' => 1]);
        self::assertSame([409, 'This revision is already published.'], [$this->code(), $this->message()]);
        $this->call('POST', "/api/communications/templates/$id/publish", $this->admin, ['expectedVersion' => 0]);
        self::assertSame(409, $this->code(), 'stale version');

        $this->call('POST', "/api/communications/templates/$id/archive", $this->admin, ['expectedVersion' => 1]);
        self::assertSame(200, $this->code());
        $this->call('POST', "/api/communications/templates/$id/publish", $this->admin, ['expectedVersion' => 2]);
        self::assertSame([409, 'Archived templates cannot be published.'], [$this->code(), $this->message()]);
        $this->call('PUT', "/api/communications/templates/$id", $this->admin, ['expectedVersion' => 2] + $this->draft());
        self::assertSame([409, 'Archived templates cannot be edited.'], [$this->code(), $this->message()]);
        self::assertSame(1, $this->rows('communication_template_revisions', "template_id = '$id'"));
    }

    public function testArchivedAndUnpublishedTemplatesCannotBeRendered(): void
    {
        $id = $this->create();
        $message = 'Publish an active template for church.welcome before preparing this email.';
        $this->assertCannotRender('church.welcome', $message);
        $this->assertCannotRender('church.unknown', 'Publish an active template for church.unknown before preparing this email.');

        $this->publish($id, 0);
        self::assertSame('Welcome Ann', $this->rendering()->render('church.welcome', ['name' => 'Ann'])->subject);
        $this->call('POST', "/api/communications/templates/$id/archive", $this->admin, ['expectedVersion' => 1]);
        $this->assertCannotRender('church.welcome', $message);
    }

    public function testRenderingNeedsEveryDeclaredValueAndIgnoresExtras(): void
    {
        $id = $this->create();
        $this->publish($id, 0);

        try {
            $this->rendering()->render('church.welcome', ['other' => 'x']);
            self::fail('Expected a rejection.');
        } catch (CommunicationException $error) {
            self::assertSame('Missing template variable: name', $error->getMessage());
        }
        $rendered = $this->rendering()->render('church.welcome', ['name' => '<b>Ann & Co</b>', 'extra' => 'ignored']);
        self::assertSame('<p>Welcome &lt;b&gt;Ann &amp; Co&lt;/b&gt;</p>', $rendered->html);
        self::assertSame('Welcome <b>Ann & Co</b>', $rendered->subject, 'the subject is plain text');
    }

    private function assertCannotRender(string $key, string $message): void
    {
        try {
            $this->rendering()->render($key, ['name' => 'Ann']);
            self::fail('Expected a rejection.');
        } catch (CommunicationException $error) {
            self::assertSame($message, $error->getMessage());
        }
    }

    private function rendering(): PublishedEmailTemplates
    {
        return new PublishedEmailTemplates(new DbalTemplateStore($this->connection), new SafeEmailHtml());
    }

    public function testListSearchesNameAndKeyLiterallyAndPages(): void
    {
        $this->create('church.welcome');
        $this->create('church.farewell');

        $this->call('GET', '/api/communications/templates?search=FARE', $this->reader);
        self::assertSame(['church.farewell'], array_column($this->json()['templates'], 'key'));
        $this->call('GET', '/api/communications/templates?search=%25', $this->reader);
        self::assertSame(0, $this->json()['total'], 'a percent sign is text, not a wildcard');
        $this->call('GET', '/api/communications/templates?search=church&limit=1&offset=1', $this->reader);
        self::assertSame([1, 2], [\count($this->json()['templates']), $this->json()['total']]);
        foreach (['limit=0', 'limit=101', 'offset=-1', 'offset=100001', 'limit=abc', 'search='.str_repeat('s', 151)] as $query) {
            $this->call('GET', "/api/communications/templates?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
    }

    public function testRevisionsAreBoundedAndNeedAnExistingTemplate(): void
    {
        $id = $this->create();
        $this->call('GET', "/api/communications/templates/$id/revisions", $this->reader);
        self::assertSame([], $this->json());
        foreach (['limit=0', 'limit=101', 'offset=-1'] as $query) {
            $this->call('GET', "/api/communications/templates/$id/revisions?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('GET', '/api/communications/templates/'.Uuid::v4()->toRfc4122().'/revisions', $this->reader);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/communications/templates/'.Uuid::v4()->toRfc4122(), $this->reader);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/communications/templates/not-a-uuid', $this->reader);
        self::assertSame(400, $this->code());
    }

    public function testPreviewSanitizesEscapesAndStoresNothing(): void
    {
        $before = $this->rows('communication_templates');
        $body = ['subject' => 'Hi {{name}}', 'html' => '<p>{{name}}<script>x()</script></p>', 'variables' => ['name'], 'values' => ['name' => '<i>Ann</i> & {{x}}']];

        $this->call('POST', '/api/communications/templates/preview', $this->reader, $body);

        self::assertSame(200, $this->code(), $this->message());
        self::assertSame(['Hi <i>Ann</i> & {{x}}', '<p>&lt;i&gt;Ann&lt;/i&gt; &amp; {{x}}</p>'], [$this->json()['subject'], $this->json()['html']]);
        self::assertSame($before, $this->rows('communication_templates'));
        self::assertSame(0, $this->rows('communication_audit'));
    }

    public function testPreviewRejectsMissingUnknownAndInjectedValues(): void
    {
        $base = ['subject' => 'Hi {{name}}', 'html' => '<p>{{name}}</p>', 'variables' => ['name'], 'values' => ['name' => 'Ann']];
        foreach ([
            [['values' => []] + $base, 'Supply every declared variable exactly once, with at most 5000 characters per value.'],
            [['values' => ['name' => 'Ann', 'other' => 'x']] + $base, 'Supply every declared variable exactly once, with at most 5000 characters per value.'],
            [['values' => ['name' => "Ann\r\nBcc: x@y.test"]] + $base, 'The rendered subject must be a single line of at most 200 characters.'],
            [['variables' => []] + $base, 'Declared variables must exactly match the variables used in the subject and body.'],
        ] as [$body, $message]) {
            $this->call('POST', '/api/communications/templates/preview', $this->reader, $body);
            self::assertSame(400, $this->code());
            self::assertSame($message, $this->message());
        }
        $this->call('POST', '/api/communications/templates/preview', $this->reader, ['values' => null] + $base);
        self::assertSame(400, $this->code());
    }

    public function testEveryChangeIsAudited(): void
    {
        $id = $this->create();
        $this->call('PUT', "/api/communications/templates/$id", $this->admin, ['expectedVersion' => 0] + $this->draft());
        $this->publish($id, 1);
        $this->call('POST', "/api/communications/templates/$id/archive", $this->admin, ['expectedVersion' => 2]);

        foreach (['createtemplate', 'updatetemplate', 'publishtemplate', 'archivetemplate'] as $action) {
            self::assertSame(1, $this->rows('communication_audit', "action = 'template.$action' AND target_id = '$id' AND actor_id = '{$this->adminId}'"), $action);
        }
    }
}

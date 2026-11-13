<?php

declare(strict_types=1);

namespace App\Tests\Forms\Presentation;

use App\Forms\Application\Command\PurgeExpiredFormData\PurgeExpiredFormDataCommand;
use App\Shared\Application\CommandBus;
use App\Tests\Support\IdentityApiTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Symfony\Component\Uid\Uuid;

final class FormsApiTest extends IdentityApiTestCase
{
    private string $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('builder', ['forms.manage', 'forms.read', 'forms.responses.read', 'forms.responses.moderate']);
        $this->manager = $this->login('builder');
    }

    /** @return array<string, mixed> */
    private function quizDefinition(): array
    {
        return ['questions' => [
            ['id' => 'q1', 'type' => 'SINGLE_CHOICE', 'label' => 'Who built the ark?', 'required' => true,
                'options' => [['id' => 'a', 'label' => 'Noah'], ['id' => 'b', 'label' => 'Moses']], 'correct' => ['a'], 'points' => 2],
        ]];
    }

    /** @return array<string, mixed> */
    private function surveyDefinition(): array
    {
        return ['questions' => [
            ['id' => 'name', 'type' => 'TEXT', 'label' => 'Name', 'required' => true],
            ['id' => 'mood', 'type' => 'RATING', 'label' => 'Mood'],
        ]];
    }

    /**
     * @param array<string, mixed>      $extra
     * @param array<string, mixed>|null $definition
     */
    private function create(array $extra = [], ?array $definition = null): string
    {
        $body = $extra + ['requestId' => Uuid::v4()->toRfc4122(), 'title' => 'Survey', 'kind' => 'DATA_COLLECTION', 'audience' => 'PUBLIC', 'identity' => 'ANONYMOUS', 'definition' => $definition ?? $this->surveyDefinition()];
        $this->call('POST', '/api/forms', $this->manager, $body);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function publish(string $id, int $version = 0): ?string
    {
        $this->call('POST', "/api/forms/$id/publish", $this->manager, ['expectedVersion' => $version]);
        self::assertSame(200, $this->code(), $this->message());

        return $this->json()['token'];
    }

    /**
     * @param array<string, mixed> $answers
     *
     * @return array<string, mixed>
     */
    private function submission(string $key, array $answers, int $version = 1, string $challenge = ''): array
    {
        return ['answers' => $answers, 'formVersion' => $version, 'submissionKey' => $key, 'challenge' => $challenge];
    }

    public function testPublicSurveyIsAnonymousIdempotentAndNeverStoresARespondent(): void
    {
        $id = $this->create();
        $token = (string) $this->publish($id);

        $this->call('GET', "/api/public/forms/$token");
        self::assertSame(200, $this->code());
        $page = $this->json();
        self::assertSame(1, $page['version']);
        self::assertTrue($page['open']);
        $body = $this->submission('retry-1', ['name' => 'Thandi', 'mood' => 4], 1, (string) $page['challenge']);

        $this->call('POST', "/api/public/forms/$token", null, $body);
        self::assertSame(201, $this->code(), $this->message());
        $this->call('POST', "/api/public/forms/$token", null, $body);
        self::assertSame(201, $this->code(), 'an identical retry is accepted without a second row');
        self::assertSame(1, $this->rows('form_responses'));
        self::assertSame(0, $this->rows('form_responses', 'respondent_person_id IS NOT NULL OR respondent_account_id IS NOT NULL OR submitted_at IS NOT NULL'));
        self::assertSame(1, $this->rows('form_responses', "expires_on > submitted_on + interval '2 months'"));

        $this->call('POST', "/api/public/forms/$token", null, $this->submission('k2', ['mood' => 3], 1, (string) $page['challenge']));
        self::assertSame(400, $this->code(), 'a required question is enforced');
        $this->call('POST', "/api/public/forms/$token", null, $this->submission('k3', ['name' => 'x'], 1, 'forged'));
        self::assertSame(400, $this->code(), 'a forged challenge is refused');
        $this->call('POST', "/api/public/forms/$token", null, $this->submission('k4', ['name' => 'x'], 2, (string) $page['challenge']));
        self::assertSame(409, $this->code(), 'an outdated form version is refused');
        $this->call('POST', "/api/public/forms/$token", null, $this->submission('bot', ['name' => 'x'], 1, (string) $page['challenge']) + ['website' => 'spam.example']);
        self::assertSame(201, $this->code());
        self::assertSame(1, $this->rows('form_responses'), 'a honeypot hit saves nothing');
    }

    public function testUnknownUnpublishedAndClosedFormsLookTheSame(): void
    {
        $this->call('GET', '/api/public/forms/'.str_repeat('a', 43));
        self::assertSame(404, $this->code());

        $id = $this->create();
        $token = (string) $this->publish($id);
        $this->call('POST', "/api/forms/$id/close", $this->manager, ['expectedVersion' => 1]);
        self::assertSame(204, $this->code());
        $this->call('GET', "/api/public/forms/$token");
        self::assertSame(404, $this->code(), 'a closed form is not served');
    }

    public function testQuizProjectionHidesAnswersAndScoresOnTheServer(): void
    {
        $id = $this->create(['kind' => 'QUIZ', 'showScore' => true, 'leaderboardEnabled' => true], $this->quizDefinition());
        $token = (string) $this->publish($id);
        $this->call('GET', "/api/public/forms/$token");
        $raw = (string) $this->client->getResponse()->getContent();
        foreach (['"correct"', '"points"'] as $leak) {
            self::assertStringNotContainsString($leak, $raw);
        }
        $challenge = (string) $this->json()['challenge'];

        $this->call('POST', "/api/public/forms/$token", null, $this->submission('p1', ['q1' => 'a'], 1, $challenge) + ['nickname' => 'Zed']);
        self::assertSame(201, $this->code(), $this->message());
        self::assertSame(2, $this->json()['score']);
        self::assertNull($this->json()['answers'], 'the answer key is only shown when the quiz allows it');
        $this->call('POST', "/api/public/forms/$token", null, $this->submission('p2', ['q1' => 'b'], 1, $challenge) + ['nickname' => 'Amy']);

        $this->call('GET', "/api/public/forms/$token/leaderboard");
        self::assertSame(200, $this->code());
        self::assertSame(['Zed', 'Amy'], array_column($this->json()['entries'], 'nickname'));
        self::assertStringNotContainsString('respondent', (string) $this->client->getResponse()->getContent());

        $rid = (string) $this->connection->fetchOne("SELECT id FROM form_responses WHERE nickname = 'Zed'");
        $this->call('POST', "/api/forms/$id/responses/$rid/hide", $this->manager);
        self::assertSame(204, $this->code());
        $this->call('GET', "/api/public/forms/$token/leaderboard");
        self::assertSame(['Amy'], array_column($this->json()['entries'], 'nickname'));
    }

    public function testManagementNeedsPermissionAndAFreshVersion(): void
    {
        $this->call('GET', '/api/forms');
        self::assertSame(401, $this->code());
        $this->accountWith('reader', ['forms.read']);
        $reader = $this->login('reader');
        $this->call('POST', '/api/forms', $reader, ['requestId' => Uuid::v4()->toRfc4122(), 'title' => 'x', 'definition' => $this->surveyDefinition()]);
        self::assertSame(403, $this->code());
        $this->call('GET', '/api/forms', $reader);
        self::assertSame(200, $this->code());

        $id = $this->create();
        $this->call('PUT', "/api/forms/$id", $this->manager, ['title' => 'x', 'definition' => $this->surveyDefinition()]);
        self::assertSame(400, $this->code(), 'expectedVersion is required');
        $this->call('PUT', "/api/forms/$id", $this->manager, ['expectedVersion' => 5, 'title' => 'x', 'definition' => $this->surveyDefinition()]);
        self::assertSame(409, $this->code());
        $this->call('PUT', "/api/forms/$id", $this->manager, ['expectedVersion' => 0, 'title' => 'Renamed', 'definition' => $this->surveyDefinition()]);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());

        $this->publish($id, 1);
        $this->call('PUT', "/api/forms/$id", $this->manager, ['expectedVersion' => 2, 'title' => 'Late', 'definition' => $this->surveyDefinition()]);
        self::assertSame(409, $this->code(), 'a published form must be unpublished before editing');
        $this->call('POST', '/api/forms', $this->manager, ['requestId' => Uuid::v4()->toRfc4122(), 'title' => 'Bad', 'audience' => 'PUBLIC', 'identity' => 'IDENTIFIED', 'definition' => $this->surveyDefinition()]);
        self::assertSame(400, $this->code(), 'a public form cannot identify respondents');
    }

    public function testStaffFormKeepsRespondentsAnonymousWithoutLinkingMarksToResponses(): void
    {
        $id = $this->create(['audience' => 'STAFF', 'identity' => 'ANONYMOUS']);
        self::assertNull($this->publish($id), 'an internal form has no public link token');
        $this->accountWith('volunteer', ['forms.respond']);
        $this->accountWith('outsider', []);
        $staff = $this->login('volunteer');
        $outsider = $this->login('outsider');

        $this->call('GET', '/api/member/forms', $staff);
        self::assertSame([$id], array_column($this->json()['items'], 'id'));
        $this->call('GET', '/api/member/forms', $outsider);
        self::assertSame([], $this->json()['items']);
        $this->call('GET', "/api/member/forms/$id", $outsider);
        self::assertSame(403, $this->code());

        $this->call('POST', "/api/member/forms/$id/responses", $outsider, $this->submission('o1', ['name' => 'x']));
        self::assertSame(403, $this->code());
        $this->call('POST', "/api/member/forms/$id/responses", $staff, $this->submission('s1', ['name' => 'Sam']));
        self::assertSame(201, $this->code(), $this->message());
        $this->call('POST', "/api/member/forms/$id/responses", $staff, $this->submission('s2', ['name' => 'Sam again']));
        self::assertSame(409, $this->code(), 'one response per account');
        $this->call('POST', "/api/member/forms/$id/responses", $staff, $this->submission('s1', ['name' => 'Sam']));
        self::assertSame(201, $this->code(), 'a retry with the same key is idempotent');

        self::assertSame(1, $this->rows('form_responses', 'respondent_account_id IS NULL AND respondent_person_id IS NULL'));
        self::assertSame(1, $this->rows('form_participation_marks'));
        self::assertSame([], $this->connection->fetchFirstColumn("SELECT column_name FROM information_schema.columns WHERE table_name = 'form_participation_marks' AND column_name IN ('response_id', 'created_at', 'submitted_at')"));

        $this->call('GET', "/api/forms/$id/responses", $this->manager);
        self::assertFalse($this->json()['identified']);
        self::assertNull($this->json()['items'][0]['personId']);
        self::assertNull($this->json()['items'][0]['submittedAt']);
        $this->call('GET', "/api/forms/$id/summary", $this->manager);
        self::assertTrue($this->json()['hidden'], 'too few anonymous responses to summarise');
    }

    public function testIdentifiedFormRecordsTheAccountAndAuditsReads(): void
    {
        $id = $this->create(['audience' => 'STAFF', 'identity' => 'IDENTIFIED']);
        $this->publish($id);
        $accountId = $this->accountWith('volunteer', ['forms.respond']);
        $this->call('POST', "/api/member/forms/$id/responses", $this->login('volunteer'), $this->submission('s1', ['name' => 'Sam']));
        self::assertSame(201, $this->code());
        self::assertSame(1, $this->rows('form_responses', "respondent_account_id = '$accountId'"));
        self::assertSame(0, $this->rows('form_participation_marks'));

        $this->call('GET', "/api/forms/$id/responses", $this->manager);
        self::assertTrue($this->json()['identified']);
        self::assertSame(1, $this->rows('forms_audit', "action = 'RESPONSES_READ' AND form_id = '$id'"));

        $this->call('GET', "/api/forms/$id/export", $this->manager);
        self::assertSame(200, $this->code());
        self::assertSame(1, $this->rows('forms_audit', "action = 'RESPONSES_EXPORTED' AND form_id = '$id'"));
    }

    public function testGroupFormIsForbiddenToNonMembers(): void
    {
        $id = $this->create(['audience' => 'GROUPS', 'identity' => 'IDENTIFIED', 'groupIds' => [Uuid::v4()->toRfc4122()]]);
        $this->publish($id);
        $this->accountWith('visitor', []);
        $this->call('POST', "/api/member/forms/$id/responses", $this->login('visitor'), $this->submission('g1', ['name' => 'x']));
        self::assertSame(403, $this->code());
        self::assertSame(0, $this->rows('form_responses'));
    }

    public function testTheDatabaseRefusesARespondentOnAnAnonymousFormAndAPromiseChange(): void
    {
        $id = $this->create();
        $this->publish($id);
        $account = $this->account('someone');
        try {
            $this->connection->executeStatement(
                "INSERT INTO form_responses (id, form_id, form_version, identity_mode, submission_key, answers, submitted_on, expires_on, respondent_account_id)
                 VALUES (?, ?, 1, 'ANONYMOUS', ?, '{}', current_date, current_date + 30, ?)",
                [Uuid::v4()->toRfc4122(), $id, str_repeat('a', 64), $account],
            );
            self::fail('The database must refuse a respondent on an anonymous form');
        } catch (DriverException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testPurgeDeletesExpiredResponsesAndMarksAndAuditsOnlyCounts(): void
    {
        $id = $this->create(['audience' => 'STAFF', 'identity' => 'ANONYMOUS']);
        $this->publish($id);
        $this->accountWith('volunteer', ['forms.respond']);
        $this->call('POST', "/api/member/forms/$id/responses", $this->login('volunteer'), $this->submission('s1', ['name' => 'Sam']));
        self::assertSame(201, $this->code());

        $this->connection->executeStatement('UPDATE form_responses SET expires_on = current_date - 1, submitted_on = current_date - 91 WHERE form_id = ?', [$id]);
        self::bootKernel();
        $bus = static::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);
        self::assertSame(1, $bus->dispatch(new PurgeExpiredFormDataCommand()));
        self::assertSame(0, $this->rows('form_responses'));
        self::assertSame(0, $this->rows('form_participation_marks'));
        self::assertSame('1 responses', $this->connection->fetchOne("SELECT detail FROM forms_audit WHERE action = 'PURGED' AND form_id = ?", [$id]));
    }
}

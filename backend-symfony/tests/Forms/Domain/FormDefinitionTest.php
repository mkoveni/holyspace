<?php

declare(strict_types=1);

namespace App\Tests\Forms\Domain;

use App\Forms\Domain\FormDefinition;
use App\Forms\Domain\FormException;
use PHPUnit\Framework\TestCase;

final class FormDefinitionTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function quiz(): array
    {
        return ['questions' => [
            ['id' => 'q1', 'type' => 'SINGLE_CHOICE', 'label' => 'Who built the ark?', 'required' => true,
                'options' => [['id' => 'a', 'label' => 'Noah'], ['id' => 'b', 'label' => 'Moses']], 'correct' => ['a'], 'points' => 2],
            ['id' => 'q2', 'type' => 'MULTIPLE_CHOICE', 'label' => 'Gospels', 'required' => false,
                'options' => [['id' => 'm', 'label' => 'Mark'], ['id' => 'l', 'label' => 'Luke'], ['id' => 'x', 'label' => 'Acts']], 'correct' => ['m', 'l'], 'points' => 3],
            ['id' => 'q3', 'type' => 'TEXT', 'label' => 'First book?', 'required' => false, 'correct' => ['Genesis', 'Bereshit'], 'points' => 1],
            ['id' => 'q4', 'type' => 'NUMBER', 'label' => 'Disciples?', 'required' => false, 'correct' => ['12'], 'points' => 1, 'tolerance' => 1],
        ]];
    }

    public function testPublicProjectionNeverCarriesTheAnswerKey(): void
    {
        $json = json_encode(FormDefinition::fromArray(self::quiz(), true)->projection(), \JSON_THROW_ON_ERROR);

        foreach (['"correct"', '"points"', '"tolerance"', 'Genesis', 'Bereshit'] as $leak) {
            self::assertStringNotContainsString($leak, $json);
        }
    }

    public function testScoresOnlyFullyCorrectQuestions(): void
    {
        $def = FormDefinition::fromArray(self::quiz(), true);
        $answers = $def->validate(['q1' => 'a', 'q2' => ['l', 'm'], 'q3' => '  genesis ', 'q4' => 13]);
        $score = $def->score($answers);
        self::assertSame(7, $score->score);
        self::assertSame(7, $score->maxScore);

        $partial = $def->score($def->validate(['q1' => 'b', 'q2' => ['m'], 'q3' => 'Exodus', 'q4' => 20]));
        self::assertSame(0, $partial->score);
        self::assertSame(7, $partial->maxScore);
    }

    public function testRejectsAnswersThatDoNotBelongToTheForm(): void
    {
        $def = FormDefinition::fromArray(self::quiz(), true);
        foreach ([['q1' => 'zzz'], ['q1' => 'a', 'ghost' => 'x'], ['q1' => 'a', 'q2' => ['m', 'nope']], ['q1' => 'a', 'q4' => NAN], []] as $bad) {
            try {
                $def->validate($bad);
                self::fail('Expected rejection');
            } catch (FormException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testQuizNeedsScorableQuestionsAndCorrectAnswersMustExist(): void
    {
        $this->expectException(FormException::class);
        FormDefinition::fromArray(['questions' => [['id' => 'q', 'type' => 'TEXT', 'label' => 'Name']]], true);
    }

    public function testCorrectAnswerMustBeAnOptionOfTheQuestion(): void
    {
        $raw = self::quiz();
        $raw['questions'][0]['correct'] = ['not-an-option'];
        $this->expectException(FormException::class);
        FormDefinition::fromArray($raw, true);
    }

    public function testDataCollectionIgnoresAnswerKeysAndNormalisesAnswers(): void
    {
        $def = FormDefinition::fromArray(['questions' => [
            ['id' => 'e', 'type' => 'EMAIL', 'label' => 'Email', 'required' => true, 'correct' => ['x'], 'points' => 5],
            ['id' => 'd', 'type' => 'DATE', 'label' => 'Birthday'],
            ['id' => 'r', 'type' => 'RATING', 'label' => 'Rate us'],
            ['id' => 'c', 'type' => 'CHECKBOX', 'label' => 'Agree'],
        ]], false);
        self::assertSame(['e' => 'a@b.co', 'd' => '2020-02-29', 'r' => 5, 'c' => true], $def->validate(['e' => ' A@B.co ', 'd' => '2020-02-29', 'r' => 5, 'c' => true]));
        self::assertStringNotContainsString('correct', json_encode($def->toArray(), \JSON_THROW_ON_ERROR));
        $this->expectException(FormException::class);
        $def->validate(['e' => 'a@b.co', 'd' => '2021-02-29']);
    }

    public function testLimitsQuestionCountAndDuplicateIds(): void
    {
        $many = array_map(static fn (int $i): array => ['id' => 'q'.$i, 'type' => 'TEXT', 'label' => 'x'], range(1, 51));
        foreach ([['questions' => $many], ['questions' => [['id' => 'a', 'type' => 'TEXT', 'label' => 'x'], ['id' => 'a', 'type' => 'TEXT', 'label' => 'y']]], ['questions' => []]] as $bad) {
            try {
                FormDefinition::fromArray($bad, false);
                self::fail('Expected rejection');
            } catch (FormException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

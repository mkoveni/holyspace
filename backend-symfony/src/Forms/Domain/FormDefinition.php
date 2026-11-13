<?php

declare(strict_types=1);

namespace App\Forms\Domain;

use DateTimeImmutable;

/** The questions of one published form version, with the rules to validate answers against them and to score a quiz. */
final readonly class FormDefinition
{
    public const int MAX_QUESTIONS = 50;

    /** @param list<Question> $questions */
    private function __construct(public array $questions, public bool $quiz)
    {
    }

    /** @param array<mixed> $raw */
    public static function fromArray(array $raw, bool $quiz): self
    {
        $list = $raw['questions'] ?? null;
        if (!is_array($list) || !array_is_list($list) || [] === $list || count($list) > self::MAX_QUESTIONS) {
            throw FormException::invalid('A form needs between 1 and '.self::MAX_QUESTIONS.' questions.');
        }
        $questions = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                throw FormException::invalid('A question is malformed.');
            }
            $question = Question::fromArray($item, $quiz);
            if (isset($questions[$question->id])) {
                throw FormException::invalid('Question ids must be unique.');
            }
            $questions[$question->id] = $question;
        }
        if ($quiz && 0 === array_sum(array_map(static fn (Question $q): int => $q->points, $questions))) {
            throw FormException::invalid('A quiz needs at least one question with a correct answer and points.');
        }

        return new self(array_values($questions), $quiz);
    }

    /** @return array{questions: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['questions' => array_map(static fn (Question $q): array => $q->toArray(), $this->questions)];
    }

    /**
     * The only shape served to respondents. It is built field by field so no answer key can slip through.
     *
     * @return array{questions: list<array<string, mixed>>}
     */
    public function projection(): array
    {
        return ['questions' => array_map(static fn (Question $q): array => $q->projection(), $this->questions)];
    }

    /**
     * Checks the submitted answers against this version and returns them normalised, keyed by question id. Optional
     * questions left blank are dropped. Nothing is trusted: every id, option, length and range is verified here.
     *
     * @param array<mixed> $answers
     *
     * @return array<string, bool|float|int|string|list<string>>
     */
    public function validate(array $answers): array
    {
        $byId = [];
        foreach ($this->questions as $question) {
            $byId[$question->id] = $question;
        }
        foreach (array_keys($answers) as $id) {
            if (!isset($byId[(string) $id])) {
                throw FormException::invalid('An answer does not belong to this form.');
            }
        }
        $clean = [];
        foreach ($this->questions as $question) {
            $value = $this->normalise($question, $answers[$question->id] ?? null);
            if (null === $value) {
                if ($question->required) {
                    throw FormException::invalid('Please answer: '.$question->label);
                }
                continue;
            }
            $clean[$question->id] = $value;
        }

        return $clean;
    }

    /**
     * Scores validated answers. A question counts only when fully right: single choice by exact match, multiple choice by
     * set equality, text by a case- and space-insensitive match against any accepted answer, number within tolerance.
     *
     * @param array<string, bool|float|int|string|list<string>> $answers
     */
    public function score(array $answers): Score
    {
        $score = 0;
        $max = 0;
        $right = [];
        foreach ($this->questions as $question) {
            if (null === $question->correct) {
                continue;
            }
            $max += $question->points;
            if (array_key_exists($question->id, $answers) && self::isCorrect($question, $answers[$question->id])) {
                $score += $question->points;
                $right[] = $question->id;
            }
        }

        return new Score($score, $max, $right);
    }

    /**
     * The correct answers, for the result screen when the form shows them after submitting. Only call this when
     * `show_answers` is on for the form.
     *
     * @return array<string, list<string>>
     */
    public function answerKey(): array
    {
        $key = [];
        foreach ($this->questions as $question) {
            if (null !== $question->correct) {
                $key[$question->id] = $question->correct;
            }
        }

        return $key;
    }

    private static function isCorrect(Question $q, mixed $given): bool
    {
        $correct = $q->correct ?? [];

        return match ($q->type) {
            QuestionType::SingleChoice, QuestionType::Rating => is_scalar($given) && (string) $given === $correct[0],
            QuestionType::Checkbox => is_bool($given) && ($given ? 'true' : 'false') === $correct[0],
            QuestionType::MultipleChoice => is_array($given) && self::sameSet($given, $correct),
            QuestionType::Number => (is_int($given) || is_float($given)) && abs((float) $given - (float) $correct[0]) <= $q->tolerance + 1e-9,
            QuestionType::Text => is_string($given) && in_array(self::fold($given), array_map(self::fold(...), $correct), true),
            default => false,
        };
    }

    /**
     * @param array<mixed> $a
     * @param list<string> $b
     */
    private static function sameSet(array $a, array $b): bool
    {
        $a = array_map(static fn (mixed $v): string => (string) $v, $a);
        sort($a);
        sort($b);

        return $a === $b;
    }

    private static function fold(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? ''));
    }

    /** @return bool|float|int|string|list<string>|null */
    private function normalise(Question $q, mixed $value): bool|float|int|string|array|null
    {
        if (null === $value || '' === $value || [] === $value) {
            return null;
        }

        return match ($q->type) {
            QuestionType::Text => $this->string($value, 500),
            QuestionType::LongText => $this->string($value, 5000),
            QuestionType::Email => $this->email($value),
            QuestionType::Number => $this->number($value),
            QuestionType::Rating => $this->rating($value),
            QuestionType::Date => $this->date($value),
            QuestionType::SingleChoice => $this->single($q, $value),
            QuestionType::MultipleChoice => $this->multiple($q, $value),
            QuestionType::Checkbox => is_bool($value) ? $value : throw FormException::invalid('A checkbox answer must be true or false.'),
        };
    }

    private function string(mixed $value, int $max): string
    {
        if (!is_string($value) || mb_strlen($value) > $max || 1 === preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
            throw FormException::invalid('An answer is too long or contains control characters.');
        }
        $text = trim($value);
        if ('' === $text) {
            throw FormException::invalid('An answer is blank.');
        }

        return $text;
    }

    private function email(mixed $value): string
    {
        $email = strtolower($this->string($value, 254));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw FormException::invalid('Enter a valid email address.');
        }

        return $email;
    }

    private function number(mixed $value): int|float
    {
        if (!(is_int($value) || is_float($value)) || !is_finite((float) $value) || abs((float) $value) > 1e12) {
            throw FormException::invalid('Enter a valid number.');
        }

        return $value;
    }

    private function rating(mixed $value): int
    {
        if (!is_int($value) || $value < 1 || $value > 5) {
            throw FormException::invalid('A rating is a whole number from 1 to 5.');
        }

        return $value;
    }

    private function date(mixed $value): string
    {
        $text = $this->string($value, 10);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        if (false === $parsed || $parsed->format('Y-m-d') !== $text) {
            throw FormException::invalid('Enter a valid date (YYYY-MM-DD).');
        }

        return $text;
    }

    private function single(Question $q, mixed $value): string
    {
        if (!is_string($value) || !in_array($value, array_column($q->options, 'id'), true)) {
            throw FormException::invalid('Choose one of the listed options.');
        }

        return $value;
    }

    /** @return list<string> */
    private function multiple(Question $q, mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > count($q->options)) {
            throw FormException::invalid('Choose from the listed options.');
        }
        $ids = array_column($q->options, 'id');
        foreach ($value as $item) {
            if (!is_string($item) || !in_array($item, $ids, true)) {
                throw FormException::invalid('Choose from the listed options.');
            }
        }

        return array_values(array_unique($value));
    }
}

<?php

declare(strict_types=1);

namespace App\Forms\Domain;

/**
 * One question of a published definition. `correct` and `points` only matter in a quiz and are never part of any
 * projection a respondent can read (see FormDefinition::projection()).
 *
 * `correct` holds option ids (choices), accepted texts (text), one numeric string (number) or "true"/"false" (checkbox).
 */
final readonly class Question
{
    public const int MAX_OPTIONS = 20;
    public const int MAX_POINTS = 1000;

    /**
     * @param list<array{id: string, label: string}> $options
     * @param list<string>|null                      $correct
     */
    public function __construct(
        public string $id,
        public QuestionType $type,
        public string $label,
        public bool $required,
        public array $options,
        public ?array $correct,
        public int $points,
        public float $tolerance,
    ) {
    }

    /** @param array<mixed> $raw */
    public static function fromArray(array $raw, bool $quiz): self
    {
        $type = QuestionType::tryFrom(self::text($raw['type'] ?? null, 20));
        if (null === $type) {
            throw FormException::invalid('A question has an unsupported type.');
        }
        $id = self::text($raw['id'] ?? null, 40);
        if (1 !== preg_match('/\A[A-Za-z0-9_-]{1,40}\z/', $id)) {
            throw FormException::invalid('Question and option ids use letters, digits, - and _ (at most 40).');
        }
        $label = trim(self::text($raw['label'] ?? null, 300));
        if ('' === $label) {
            throw FormException::invalid('Every question needs a label.');
        }
        $options = self::options($type, $raw['options'] ?? []);
        $correct = $quiz ? self::correct($type, $raw['correct'] ?? null, $options) : null;
        $points = $quiz && null !== $correct ? self::points($raw['points'] ?? 1) : 0;
        $tolerance = 0.0;
        if ($quiz && QuestionType::Number === $type && isset($raw['tolerance'])) {
            $tolerance = is_int($raw['tolerance']) || is_float($raw['tolerance']) ? (float) $raw['tolerance'] : -1.0;
            if ($tolerance < 0.0 || !is_finite($tolerance)) {
                throw FormException::invalid('A number tolerance must be zero or more.');
            }
        }

        return new self($id, $type, $label, true === ($raw['required'] ?? false), $options, $correct, $points, $tolerance);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = ['id' => $this->id, 'type' => $this->type->value, 'label' => $this->label, 'required' => $this->required, 'options' => $this->options];
        if (null !== $this->correct) {
            $out['correct'] = $this->correct;
            $out['points'] = $this->points;
            $out['tolerance'] = $this->tolerance;
        }

        return $out;
    }

    /**
     * What a respondent may see: never `correct`, `points` or `tolerance`.
     *
     * @return array<string, mixed>
     */
    public function projection(): array
    {
        return ['id' => $this->id, 'type' => $this->type->value, 'label' => $this->label, 'required' => $this->required, 'options' => $this->options];
    }

    private static function text(mixed $value, int $max): string
    {
        if (!is_string($value) || mb_strlen($value) > $max || 1 === preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
            throw FormException::invalid('A question field is missing or too long.');
        }

        return $value;
    }

    /** @return list<array{id: string, label: string}> */
    private static function options(QuestionType $type, mixed $raw): array
    {
        if (!$type->hasOptions()) {
            return [];
        }
        if (!is_array($raw) || !array_is_list($raw) || count($raw) < 2 || count($raw) > self::MAX_OPTIONS) {
            throw FormException::invalid('A choice question needs between 2 and '.self::MAX_OPTIONS.' options.');
        }
        $options = [];
        foreach ($raw as $option) {
            if (!is_array($option)) {
                throw FormException::invalid('An option is malformed.');
            }
            $id = self::text($option['id'] ?? null, 40);
            $label = trim(self::text($option['label'] ?? null, 200));
            if (1 !== preg_match('/\A[A-Za-z0-9_-]{1,40}\z/', $id) || '' === $label || isset($options[$id])) {
                throw FormException::invalid('Options need a unique id and a label.');
            }
            $options[$id] = ['id' => $id, 'label' => $label];
        }

        return array_values($options);
    }

    /**
     * @param list<array{id: string, label: string}> $options
     *
     * @return list<string>|null
     */
    private static function correct(QuestionType $type, mixed $raw, array $options): ?array
    {
        if (null === $raw) {
            return null;
        }
        if (!is_array($raw) || !array_is_list($raw) || [] === $raw || count($raw) > self::MAX_OPTIONS) {
            throw FormException::invalid('The correct answer of a question is malformed.');
        }
        $values = [];
        foreach ($raw as $value) {
            if (!is_string($value) || mb_strlen($value) > 200) {
                throw FormException::invalid('The correct answer of a question is malformed.');
            }
            $values[] = $value;
        }
        $values = array_values(array_unique($values));
        $ids = array_column($options, 'id');
        $ok = match ($type) {
            QuestionType::SingleChoice => 1 === count($values) && [] === array_diff($values, $ids),
            QuestionType::MultipleChoice => [] === array_diff($values, $ids),
            QuestionType::Checkbox => 1 === count($values) && in_array($values[0], ['true', 'false'], true),
            QuestionType::Number => 1 === count($values) && is_numeric($values[0]) && is_finite((float) $values[0]),
            QuestionType::Rating => 1 === count($values) && 1 === preg_match('/\A[1-5]\z/', $values[0]),
            QuestionType::Text => true,
            default => false,
        };
        if (!$ok) {
            throw FormException::invalid('A question cannot be scored with that correct answer.');
        }

        return $values;
    }

    private static function points(mixed $raw): int
    {
        if (!is_int($raw) || $raw < 0 || $raw > self::MAX_POINTS) {
            throw FormException::invalid('Points are a whole number from 0 to '.self::MAX_POINTS.'.');
        }

        return $raw;
    }
}

<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetSummary;

use App\Forms\Domain\FormDefinition;
use App\Forms\Domain\Question;
use App\Forms\Domain\QuestionType;

/** Per-question totals computed from the stored answers. Free text is only counted, never echoed into a summary. */
final class SummaryBuilder
{
    private function __construct()
    {
    }

    /**
     * @param list<array<string, mixed>> $answerSets
     *
     * @return list<array<string, mixed>>
     */
    public static function build(FormDefinition $definition, array $answerSets): array
    {
        return array_map(static fn (Question $q): array => self::question($q, $answerSets), $definition->questions);
    }

    /**
     * @param list<array<string, mixed>> $sets
     *
     * @return array<string, mixed>
     */
    private static function question(Question $q, array $sets): array
    {
        $values = [];
        foreach ($sets as $set) {
            if (array_key_exists($q->id, $set)) {
                $values[] = $set[$q->id];
            }
        }
        $out = ['id' => $q->id, 'label' => $q->label, 'type' => $q->type->value, 'answered' => count($values)];
        switch ($q->type) {
            case QuestionType::SingleChoice:
            case QuestionType::MultipleChoice:
                $counts = array_fill_keys(array_column($q->options, 'id'), 0);
                foreach ($values as $value) {
                    foreach ((array) $value as $id) {
                        if (isset($counts[(string) $id])) {
                            ++$counts[(string) $id];
                        }
                    }
                }
                $out['options'] = array_map(static fn (array $o): array => ['id' => $o['id'], 'label' => $o['label'], 'count' => $counts[$o['id']]], $q->options);
                break;
            case QuestionType::Rating:
            case QuestionType::Number:
                $numbers = array_values(array_filter($values, 'is_numeric'));
                $out['average'] = [] === $numbers ? null : round(array_sum($numbers) / count($numbers), 2);
                $out['min'] = [] === $numbers ? null : min($numbers);
                $out['max'] = [] === $numbers ? null : max($numbers);
                if (QuestionType::Rating === $q->type) {
                    $out['distribution'] = array_map(static fn (int $n): int => count(array_filter($numbers, static fn (mixed $v): bool => (int) $v === $n)), range(1, 5));
                }
                break;
            case QuestionType::Checkbox:
                $out['yes'] = count(array_filter($values, static fn (mixed $v): bool => true === $v));
                $out['no'] = count($values) - $out['yes'];
                break;
            default:
                break;
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Forms\Domain;

enum QuestionType: string
{
    case Text = 'TEXT';
    case LongText = 'LONG_TEXT';
    case Email = 'EMAIL';
    case Number = 'NUMBER';
    case Rating = 'RATING';
    case Date = 'DATE';
    case SingleChoice = 'SINGLE_CHOICE';
    case MultipleChoice = 'MULTIPLE_CHOICE';
    case Checkbox = 'CHECKBOX';

    public function hasOptions(): bool
    {
        return self::SingleChoice === $this || self::MultipleChoice === $this;
    }
}

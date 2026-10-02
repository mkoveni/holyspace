<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Exception;
final class InvalidLifeEventNotes extends \InvalidArgumentException
{
    public static function tooLong(): self { return new self('Life event notes cannot exceed 2000 characters.'); }
}

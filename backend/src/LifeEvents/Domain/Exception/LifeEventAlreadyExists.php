<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Exception;
use RuntimeException;
final class LifeEventAlreadyExists extends RuntimeException
{
    public static function forParticipants(string $type,string $date):self{return new self(sprintf('A %s life event already exists for the supplied participants on %s.',$type,$date));}
    public static function forPersonAndType(string $personId,string $type,string $date):self{return new self(sprintf('A %s life event already exists for person %s on %s.',$type,$personId,$date));}
}

<?php
declare(strict_types=1);
namespace App\LifeEvents\Application\Port;
use App\LifeEvents\Domain\ValueObject\PersonId;
interface PersonExistenceChecker { public function exists(PersonId $personId): bool; }

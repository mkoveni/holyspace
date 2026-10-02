<?php
declare(strict_types=1);
namespace App\LifeEvents\Infrastructure\People;
use App\LifeEvents\Application\Port\PersonExistenceChecker;
use App\LifeEvents\Domain\ValueObject\PersonId;
use Doctrine\DBAL\Connection;
final readonly class PeoplePersonExistenceChecker implements PersonExistenceChecker {public function __construct(private Connection $connection){} public function exists(PersonId $personId):bool{return (bool)$this->connection->fetchOne('SELECT 1 FROM people_persons WHERE id=:id',['id'=>$personId->toString()]);}}


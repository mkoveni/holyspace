<?php declare(strict_types=1);
namespace App\People\Application\Query\ListPeople;
use App\People\Application\DTO\PersonView; use App\People\Application\ReadModel\PeopleReadRepository;
final readonly class ListPeopleHandler { public function __construct(private PeopleReadRepository $read) {} public function __invoke(ListPeopleQuery $q): array { return array_map(static fn(array $r): PersonView => new PersonView($r['id'],$r['first_name'],$r['last_name'],$r['date_of_birth'],$r['gender'],$r['email'],$r['phone'],$r['membership_status'],$r['created_at'],$r['updated_at']), $this->read->people()); } }

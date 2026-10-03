<?php declare(strict_types=1);
namespace App\People\Application\Query\ListFamilies;
use App\People\Application\ReadModel\PeopleReadRepository;
final readonly class ListFamiliesHandler { public function __construct(private PeopleReadRepository $read) {} public function __invoke(ListFamiliesQuery $q): array { return array_map(static fn(array $r): array => ['id'=>$r['id'],'name'=>$r['name'],'memberCount'=>(int)$r['member_count'],'createdAt'=>$r['created_at'],'updatedAt'=>$r['updated_at']], $this->read->families()); } }

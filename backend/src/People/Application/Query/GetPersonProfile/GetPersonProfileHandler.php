<?php declare(strict_types=1);
namespace App\People\Application\Query\GetPersonProfile;
use App\People\Application\ReadModel\PeopleReadRepository;
final readonly class GetPersonProfileHandler { public function __construct(private PeopleReadRepository $read) {} public function __invoke(GetPersonProfileQuery $q): array { $p=$this->read->profile($q->personId); return ['addresses'=>$p['addresses'],'communicationOptions'=>$p['communications'],'education'=>$p['education'],'employment'=>$p['employment']]; } }

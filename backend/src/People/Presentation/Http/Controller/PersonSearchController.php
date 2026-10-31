<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\Controller;

use App\People\Application\Query\SearchPeople\SearchPeopleHandler;
use App\People\Application\Query\SearchPeople\SearchPeopleQuery;
use App\People\Presentation\Http\DTO\PersonSearchFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class PersonSearchController extends AbstractController
{
    #[Route('/api/people/search', name: 'app_people_search', methods: ['GET'], priority: 20, format: 'json')]
    public function __invoke(
        SearchPeopleHandler $people,
        #[MapQueryString(validationFailedStatusCode: 422)]
        PersonSearchFilter $filter = new PersonSearchFilter(),
    ): JsonResponse {
        return $this->json($people(new SearchPeopleQuery(trim($filter->q), $filter->limit, $filter->excludeIds, $filter->ids)));
    }
}

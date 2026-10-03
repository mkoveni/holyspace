<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\Controller;

use App\People\Application\Command\CreateRelationship\CreateRelationshipCommand;
use App\People\Application\Command\EndRelationship\EndRelationshipCommand;
use App\People\Application\Query\GetPersonRelationships\GetPersonRelationshipsHandler;
use App\People\Application\Query\GetPersonRelationships\GetPersonRelationshipsQuery;
use App\People\Presentation\Http\Service\CommandDispatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RelationshipsController extends AbstractController
{
    public function __construct(
        private readonly CommandDispatcher $commands,
        private readonly GetPersonRelationshipsHandler $getRelationships,
    ) {
    }

    #[Route('/api/people/{personId}/relationships', methods: ['GET'], name: 'app_people_presentation_http_people_relationships')]
    public function relationships(string $personId): JsonResponse
    {
        return new JsonResponse(array_map(static fn ($view) => get_object_vars($view), ($this->getRelationships)(new GetPersonRelationshipsQuery($personId))));
    }

    #[Route('/api/people/{personId}/relationships', methods: ['POST'], name: 'app_people_presentation_http_people_createrelationship')]
    public function createRelationship(string $personId, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $id = $this->commands->dispatch(new CreateRelationshipCommand(
            $personId,
            (string) ($data['relatedPersonId'] ?? ''),
            (string) ($data['type'] ?? ''),
        ));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }

    #[Route('/api/relationships/{id}/end', methods: ['POST'], name: 'app_people_presentation_http_people_endrelationship')]
    public function endRelationship(string $id, Request $request): JsonResponse
    {
        $this->commands->dispatch(new EndRelationshipCommand(
            $id,
            (string) (($request->toArray())['endedAt'] ?? date('Y-m-d H:i:s')),
        ));
        return new JsonResponse(null, 204);
    }
}

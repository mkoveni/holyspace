<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\Controller;

use App\People\Application\Command\SetFamilyPrimaryContact\SetFamilyPrimaryContactCommand;
use App\People\Application\Command\SetFamilyPrimaryContact\SetFamilyPrimaryContactHandler;
use App\People\Application\Query\FamilyOverview\FamilyOverviewHandler;
use App\People\Application\Query\FamilyOverview\FamilyOverviewQuery;
use App\People\Presentation\Http\DTO\SetFamilyPrimaryContactPayload;
use App\Shared\Presentation\Http\DTO\ErrorResponse;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class FamilyOverviewController extends AbstractController
{
    public function __construct(private readonly FamilyOverviewHandler $overview, private readonly SetFamilyPrimaryContactHandler $setContact)
    {
    }

    #[Route('/api/family-overviews', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(($this->overview)(new FamilyOverviewQuery()));
    }

    #[Route('/api/families/{id}/overview', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $families = ($this->overview)(new FamilyOverviewQuery($id));
        if ([] === $families) {
            throw $this->createNotFoundException('Family not found.');
        }

        return $this->json($families[0]);
    }

    #[Route('/api/families/{id}/primary-contact', methods: ['PUT'], format: 'json')]
    public function setContact(
        string $id,
        #[MapRequestPayload]
        SetFamilyPrimaryContactPayload $payload,
    ): JsonResponse {
        try {
            ($this->setContact)(new SetFamilyPrimaryContactCommand($id, $payload->personId));
        } catch (InvalidArgumentException $error) {
            return $this->json(new ErrorResponse($error->getMessage()), 422);
        } catch (RuntimeException $error) {
            return $this->json(new ErrorResponse($error->getMessage()), 404);
        }

        return new JsonResponse(null, 204);
    }
}

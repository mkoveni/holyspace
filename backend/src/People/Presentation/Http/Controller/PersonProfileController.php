<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\Controller;

use App\People\Application\Command\AddAddress\AddAddressCommand;
use App\People\Application\Command\AddCommunicationOption\AddCommunicationOptionCommand;
use App\People\Application\Command\AddEducation\AddEducationCommand;
use App\People\Application\Command\AddEmployment\AddEmploymentCommand;
use App\People\Application\Query\GetPersonProfile\GetPersonProfileHandler;
use App\People\Application\Query\GetPersonProfile\GetPersonProfileQuery;
use App\People\Presentation\Http\Service\CommandDispatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PersonProfileController extends AbstractController
{
    public function __construct(
        private readonly CommandDispatcher $commands,
        private readonly GetPersonProfileHandler $profile,
    ) {
    }

    #[Route('/api/people/{personId}/profile', methods: ['GET'], name: 'app_people_presentation_http_people_profile')]
    public function profile(string $personId): JsonResponse
    {
        return new JsonResponse(($this->profile)(new GetPersonProfileQuery($personId)));
    }

    #[Route('/api/people/{personId}/addresses', methods: ['POST'], name: 'app_people_presentation_http_people_addaddress')]
    public function addAddress(string $personId, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $id = $this->commands->dispatch(new AddAddressCommand(
            $personId,
            (string) ($data['type'] ?? 'residential'),
            (string) ($data['line1'] ?? ''),
            isset($data['line2']) ? (string) $data['line2'] : null,
            (string) ($data['city'] ?? ''),
            isset($data['province']) ? (string) $data['province'] : null,
            isset($data['postalCode']) ? (string) $data['postalCode'] : null,
            (string) ($data['country'] ?? 'South Africa'),
        ));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }

    #[Route('/api/people/{personId}/communication-options', methods: ['POST'], name: 'app_people_presentation_http_people_communication')]
    public function communication(string $personId, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $id = $this->commands->dispatch(new AddCommunicationOptionCommand(
            $personId,
            (string) ($data['channel'] ?? ''),
            (string) ($data['value'] ?? ''),
            (bool) ($data['preferred'] ?? false),
        ));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }

    #[Route('/api/people/{personId}/education', methods: ['POST'], name: 'app_people_presentation_http_people_education')]
    public function education(string $personId, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $id = $this->commands->dispatch(new AddEducationCommand(
            $personId,
            (string) ($data['institution'] ?? ''),
            (string) ($data['qualification'] ?? ''),
            isset($data['startDate']) ? (string) $data['startDate'] : null,
            isset($data['endDate']) ? (string) $data['endDate'] : null,
            isset($data['fieldOfStudy']) ? (string) $data['fieldOfStudy'] : null,
            isset($data['notes']) ? (string) $data['notes'] : null,
        ));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }

    #[Route('/api/people/{personId}/employment', methods: ['POST'], name: 'app_people_presentation_http_people_employment')]
    public function employment(string $personId, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $id = $this->commands->dispatch(new AddEmploymentCommand(
            $personId,
            (string) ($data['employer'] ?? ''),
            (string) ($data['jobTitle'] ?? ''),
            isset($data['startDate']) ? (string) $data['startDate'] : null,
            isset($data['endDate']) ? (string) $data['endDate'] : null,
            isset($data['industry']) ? (string) $data['industry'] : null,
            isset($data['workEmail']) ? (string) $data['workEmail'] : null,
            isset($data['workPhone']) ? (string) $data['workPhone'] : null,
            isset($data['notes']) ? (string) $data['notes'] : null,
        ));
        return new JsonResponse(['id' => $id?->toString()], 201);
    }
}

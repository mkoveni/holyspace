<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Presentation;

use App\ChildrenMinistry\Reporting\Application\Query\GetMinistryOverview\GetMinistryOverviewHandler;
use App\ChildrenMinistry\Reporting\Application\Query\GetMinistryOverview\GetMinistryOverviewQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The ministry week. Any one of children.read, children.safeguarding or children.incidents.manage opens it; each section
 * then needs its own permission and is null without it. There is no single HTTP attribute for "any of": the handler enforces it.
 */
#[Route('/api/children/overview')]
final readonly class MinistryOverviewController
{
    public function __construct(private GetMinistryOverviewHandler $handler)
    {
    }

    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse(ReportingJson::overview($this->handler->handle(new GetMinistryOverviewQuery())));
    }
}

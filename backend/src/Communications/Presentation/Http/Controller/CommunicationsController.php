<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Http\Controller;

use App\Communications\Application\Command\CancelCampaign\CancelCampaignCommand;
use App\Communications\Application\Command\CancelCampaign\CancelCampaignHandler;
use App\Communications\Application\Command\CreateCampaign\CreateCampaignCommand;
use App\Communications\Application\Command\CreateCampaign\CreateCampaignHandler;
use App\Communications\Application\Command\QueueCampaign\QueueCampaignCommand;
use App\Communications\Application\Command\QueueCampaign\QueueCampaignHandler;
use App\Communications\Application\Command\ResolveDelivery\ResolveDeliveryCommand;
use App\Communications\Application\Command\ResolveDelivery\ResolveDeliveryHandler;
use App\Communications\Application\Command\SaveCommunicationPreference\SaveCommunicationPreferenceCommand;
use App\Communications\Application\Command\SaveCommunicationPreference\SaveCommunicationPreferenceHandler;
use App\Communications\Application\Command\SaveMessageTemplate\SaveMessageTemplateCommand;
use App\Communications\Application\Command\SaveMessageTemplate\SaveMessageTemplateHandler;
use App\Communications\Application\Command\WithdrawCommunicationConsent\WithdrawCommunicationConsentCommand;
use App\Communications\Application\Command\WithdrawCommunicationConsent\WithdrawCommunicationConsentHandler;
use App\Communications\Application\Query\GetCommunicationsOverview\GetCommunicationsOverviewHandler;
use App\Communications\Application\Query\GetCommunicationsOverview\GetCommunicationsOverviewQuery;
use App\Communications\Presentation\Http\DTO\CampaignPayload;
use App\Communications\Presentation\Http\DTO\DeliveryResolutionPayload;
use App\Communications\Presentation\Http\DTO\PreferencePayload;
use App\Communications\Presentation\Http\DTO\TemplatePayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/communications', format: 'json')]
#[IsGranted('communications.manage')]
final class CommunicationsController extends WorkflowController
{
    public function __construct(
        private readonly GetCommunicationsOverviewHandler $getCommunicationsOverviewHandler,
        private readonly ResolveDeliveryHandler $resolveDeliveryHandler,
        private readonly SaveMessageTemplateHandler $saveMessageTemplateHandler,
        private readonly SaveCommunicationPreferenceHandler $saveCommunicationPreferenceHandler,
        private readonly WithdrawCommunicationConsentHandler $withdrawCommunicationConsentHandler,
        private readonly CreateCampaignHandler $createCampaignHandler,
        private readonly QueueCampaignHandler $queueCampaignHandler,
        private readonly CancelCampaignHandler $cancelCampaignHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->result(fn () => ($this->getCommunicationsOverviewHandler)(new GetCommunicationsOverviewQuery()));
    }

    #[Route('/deliveries/{id}/resolve', methods: ['PUT'])]
    public function resolve(
        string $id,
        #[MapRequestPayload]
        DeliveryResolutionPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->resolveDeliveryHandler)(new ResolveDeliveryCommand($id, $p->status, $p->note)));
    }

    #[Route('/templates', methods: ['POST'])]
    public function template(
        #[MapRequestPayload]
        TemplatePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveMessageTemplateHandler)(new SaveMessageTemplateCommand(get_object_vars($p))), 201);
    }

    #[Route('/preferences', methods: ['POST'])]
    public function preference(
        #[MapRequestPayload]
        PreferencePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->saveCommunicationPreferenceHandler)(new SaveCommunicationPreferenceCommand(get_object_vars($p))), 201);
    }

    #[Route('/preferences/{id}/withdraw', methods: ['POST'])]
    public function withdraw(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->withdrawCommunicationConsentHandler)(new WithdrawCommunicationConsentCommand($id)));
    }

    #[Route('/campaigns', methods: ['POST'])]
    public function campaign(
        #[MapRequestPayload]
        CampaignPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->createCampaignHandler)(new CreateCampaignCommand(get_object_vars($p))), 201);
    }

    #[Route('/campaigns/{id}/queue', methods: ['POST'])]
    public function queue(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->queueCampaignHandler)(new QueueCampaignCommand($id)));
    }

    #[Route('/campaigns/{id}/cancel', methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->cancelCampaignHandler)(new CancelCampaignCommand($id)));
    }
}

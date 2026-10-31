<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\Controller;

use App\Giving\Application\Command\ChangePledgeStatus\ChangePledgeStatusCommand;
use App\Giving\Application\Command\ChangePledgeStatus\ChangePledgeStatusHandler;
use App\Giving\Application\Command\CreateFund\CreateFundCommand;
use App\Giving\Application\Command\CreateFund\CreateFundHandler;
use App\Giving\Application\Command\CreatePledge\CreatePledgeCommand;
use App\Giving\Application\Command\CreatePledge\CreatePledgeHandler;
use App\Giving\Application\Command\IssueReceipt\IssueReceiptCommand;
use App\Giving\Application\Command\IssueReceipt\IssueReceiptHandler;
use App\Giving\Application\Command\ReconcileGiving\ReconcileGivingCommand;
use App\Giving\Application\Command\ReconcileGiving\ReconcileGivingHandler;
use App\Giving\Application\Command\RecordGift\RecordGiftCommand;
use App\Giving\Application\Command\RecordGift\RecordGiftHandler;
use App\Giving\Application\Command\ReverseGift\ReverseGiftCommand;
use App\Giving\Application\Command\ReverseGift\ReverseGiftHandler;
use App\Giving\Application\Query\GetGivingOverview\GetGivingOverviewHandler;
use App\Giving\Application\Query\GetGivingOverview\GetGivingOverviewQuery;
use App\Giving\Application\Query\GetReceiptDetails\GetReceiptDetailsHandler;
use App\Giving\Application\Query\GetReceiptDetails\GetReceiptDetailsQuery;
use App\Giving\Presentation\Http\DTO\BatchPayload;
use App\Giving\Presentation\Http\DTO\FundPayload;
use App\Giving\Presentation\Http\DTO\GiftPayload;
use App\Giving\Presentation\Http\DTO\GiftReversalPayload;
use App\Giving\Presentation\Http\DTO\GivingFilter;
use App\Giving\Presentation\Http\DTO\PledgePayload;
use App\Giving\Presentation\Http\DTO\PledgeStatusPayload;
use App\Giving\Presentation\Http\DTO\ReceiptPayload;
use App\Giving\Presentation\Http\DTO\ReversalPayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/giving', format: 'json')]
#[IsGranted('giving.manage')]
final class GivingController extends WorkflowController
{
    public function __construct(
        private readonly GetGivingOverviewHandler $getGivingOverviewHandler,
        private readonly CreateFundHandler $createFundHandler,
        private readonly RecordGiftHandler $recordGiftHandler,
        private readonly IssueReceiptHandler $issueReceiptHandler,
        private readonly GetReceiptDetailsHandler $getReceiptDetailsHandler,
        private readonly ReverseGiftHandler $reverseGiftHandler,
        private readonly CreatePledgeHandler $createPledgeHandler,
        private readonly ChangePledgeStatusHandler $changePledgeStatusHandler,
        private readonly ReconcileGivingHandler $reconcileGivingHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryString]
        GivingFilter $filter = new GivingFilter(),
    ): JsonResponse {
        return $this->result(fn () => ($this->getGivingOverviewHandler)(new GetGivingOverviewQuery($filter->from, $filter->to, $filter->fundId)));
    }

    #[Route('/funds', methods: ['POST'])]
    public function fund(
        #[MapRequestPayload]
        FundPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->createFundHandler)(new CreateFundCommand($p->name)), 201);
    }

    #[Route('/gifts', methods: ['POST'])]
    public function gift(
        #[MapRequestPayload]
        GiftPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->recordGiftHandler)(new RecordGiftCommand(get_object_vars($p))), 201);
    }

    #[Route('/receipts', methods: ['POST'])]
    public function receipt(
        #[MapRequestPayload]
        ReceiptPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->issueReceiptHandler)(new IssueReceiptCommand($p->giftId)), 201);
    }

    #[Route('/receipts/{id}', methods: ['GET'])]
    public function receiptDetails(string $id): JsonResponse
    {
        return $this->result(fn () => ($this->getReceiptDetailsHandler)(new GetReceiptDetailsQuery($id)));
    }

    #[Route('/reversals', methods: ['POST'])]
    public function reversal(
        #[MapRequestPayload]
        GiftReversalPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->reverseGiftHandler)(new ReverseGiftCommand($p->giftId, $p->reversedOn, $p->reason)), 201);
    }

    #[Route('/gifts/{id}/reverse', methods: ['POST'])]
    public function reverse(
        string $id,
        #[MapRequestPayload]
        ReversalPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->reverseGiftHandler)(new ReverseGiftCommand($id, $p->reversedOn, $p->reason)), 201);
    }

    #[Route('/pledges', methods: ['POST'])]
    public function pledge(
        #[MapRequestPayload]
        PledgePayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->createPledgeHandler)(new CreatePledgeCommand(get_object_vars($p))), 201);
    }

    #[Route('/pledges/{id}/status', methods: ['POST'])]
    public function pledgeStatus(
        string $id,
        #[MapRequestPayload]
        PledgeStatusPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->changePledgeStatusHandler)(new ChangePledgeStatusCommand($id, $p->status)));
    }

    #[Route('/batches', methods: ['POST'])]
    public function batch(
        #[MapRequestPayload]
        BatchPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->reconcileGivingHandler)(new ReconcileGivingCommand(get_object_vars($p))), 201);
    }
}

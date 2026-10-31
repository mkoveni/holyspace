<?php

declare(strict_types=1);

namespace App\Attendance\Presentation\Http\Controller;

use App\Attendance\Application\Command\CorrectAttendance\CorrectAttendanceCommand;
use App\Attendance\Application\Command\CorrectAttendance\CorrectAttendanceHandler;
use App\Attendance\Application\Command\CorrectVisitorCount\CorrectVisitorCountCommand;
use App\Attendance\Application\Command\CorrectVisitorCount\CorrectVisitorCountHandler;
use App\Attendance\Application\Command\RecordAttendance\RecordAttendanceCommand;
use App\Attendance\Application\Command\RecordAttendance\RecordAttendanceHandler;
use App\Attendance\Application\Command\RecordVisitors\RecordVisitorsCommand;
use App\Attendance\Application\Command\RecordVisitors\RecordVisitorsHandler;
use App\Attendance\Application\Query\GetAttendance\GetAttendanceHandler;
use App\Attendance\Application\Query\GetAttendance\GetAttendanceQuery;
use App\Attendance\Presentation\Http\DTO\AttendanceFilter;
use App\Attendance\Presentation\Http\DTO\CorrectionPayload;
use App\Attendance\Presentation\Http\DTO\EntryPayload;
use App\Attendance\Presentation\Http\DTO\VisitorCorrectionPayload;
use App\Attendance\Presentation\Http\DTO\VisitorsPayload;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/attendance', format: 'json')]
#[IsGranted('attendance.manage')]
final class AttendanceController extends WorkflowController
{
    public function __construct(
        private readonly GetAttendanceHandler $getAttendanceHandler,
        private readonly RecordAttendanceHandler $recordAttendanceHandler,
        private readonly CorrectAttendanceHandler $correctAttendanceHandler,
        private readonly RecordVisitorsHandler $recordVisitorsHandler,
        private readonly CorrectVisitorCountHandler $correctVisitorCountHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryString]
        AttendanceFilter $filter = new AttendanceFilter(),
    ): JsonResponse {
        return $this->result(fn () => ($this->getAttendanceHandler)(new GetAttendanceQuery($filter->personId, $filter->occurrenceId)));
    }

    #[Route('/entries', methods: ['POST'])]
    public function entry(
        #[MapRequestPayload]
        EntryPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->recordAttendanceHandler)(new RecordAttendanceCommand(get_object_vars($p))), 201);
    }

    #[Route('/entries/{id}', methods: ['PUT'])]
    public function correction(
        string $id,
        #[MapRequestPayload]
        CorrectionPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->correctAttendanceHandler)(new CorrectAttendanceCommand($id, $p->status, $p->reason)));
    }

    #[Route('/visitors', methods: ['POST'])]
    public function visitors(
        #[MapRequestPayload]
        VisitorsPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->recordVisitorsHandler)(new RecordVisitorsCommand(get_object_vars($p))), 201);
    }

    #[Route('/visitors/{id}', methods: ['PUT'])]
    public function visitorCorrection(
        string $id,
        #[MapRequestPayload]
        VisitorCorrectionPayload $p,
    ): JsonResponse {
        return $this->result(fn () => ($this->correctVisitorCountHandler)(new CorrectVisitorCountCommand($id, $p->count, $p->reason)));
    }
}

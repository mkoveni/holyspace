<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Presentation;

use App\ChildrenMinistry\Reporting\Application\AttendanceReportFilter;
use App\ChildrenMinistry\Reporting\Application\Query\ExportAttendanceReport\ExportAttendanceReportHandler;
use App\ChildrenMinistry\Reporting\Application\Query\ExportAttendanceReport\ExportAttendanceReportQuery;
use App\ChildrenMinistry\Reporting\Application\Query\GetAttendanceReport\GetAttendanceReportHandler;
use App\ChildrenMinistry\Reporting\Application\Query\GetAttendanceReport\GetAttendanceReportQuery;
use App\ChildrenMinistry\Reporting\Application\Query\GetAttendanceTimeline\GetAttendanceTimelineHandler;
use App\ChildrenMinistry\Reporting\Application\Query\GetAttendanceTimeline\GetAttendanceTimelineQuery;
use App\Shared\Domain\CalendarDate;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Read-only attendance reports. Reading needs children.read; the CSV export also needs children.manage. */
#[Route('/api/children')]
final readonly class AttendanceReportingController
{
    public function __construct(
        private GetAttendanceReportHandler $report,
        private ExportAttendanceReportHandler $export,
        private GetAttendanceTimelineHandler $timeline,
    ) {
    }

    #[IsGranted('children.read')]
    #[Route('/attendance-reports', methods: ['GET'])]
    public function report(
        #[MapQueryParameter] ?string $from = null,
        #[MapQueryParameter] ?string $until = null,
        #[MapQueryParameter] ?string $programmeId = null,
        #[MapQueryParameter] ?string $locationId = null,
        #[MapQueryParameter] ?string $childId = null,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] string $outcome = 'ALL',
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 25,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        $filter = self::filter($from, $until, $programmeId, $locationId, $childId, $search, $outcome);

        return new JsonResponse(ReportingJson::report($this->report->handle(new GetAttendanceReportQuery($filter, $limit, $offset))));
    }

    #[IsGranted('children.read')]
    #[IsGranted('children.manage')]
    #[Route('/attendance-reports/export', methods: ['GET'])]
    public function export(
        #[MapQueryParameter] ?string $from = null,
        #[MapQueryParameter] ?string $until = null,
        #[MapQueryParameter] ?string $programmeId = null,
        #[MapQueryParameter] ?string $locationId = null,
        #[MapQueryParameter] ?string $childId = null,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] string $outcome = 'ALL',
    ): Response {
        $result = $this->export->handle(new ExportAttendanceReportQuery(self::filter($from, $until, $programmeId, $locationId, $childId, $search, $outcome)));

        return new Response(AttendanceReportCsv::render($result), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="children-attendance.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    #[IsGranted('children.read')]
    #[Route('/attendance/{attendanceId}/timeline', methods: ['GET'])]
    public function timeline(
        #[PathUuid] string $attendanceId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 50,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): JsonResponse {
        return new JsonResponse(ReportingJson::report($this->timeline->handle(new GetAttendanceTimelineQuery($attendanceId, $limit, $offset))));
    }

    private static function filter(?string $from, ?string $until, ?string $programmeId, ?string $locationId, ?string $childId, string $search, string $outcome): AttendanceReportFilter
    {
        return new AttendanceReportFilter(
            CalendarDate::parse($from ?? throw new BadRequestException('From is required.')),
            CalendarDate::parse($until ?? throw new BadRequestException('Until is required.')),
            null === $programmeId ? null : Json::uuid($programmeId),
            null === $locationId ? null : Json::uuid($locationId),
            null === $childId ? null : Json::uuid($childId),
            $search,
            $outcome,
        );
    }
}

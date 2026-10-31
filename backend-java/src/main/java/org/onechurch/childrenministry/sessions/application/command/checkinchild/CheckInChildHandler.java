package org.onechurch.childrenministry.sessions.application.command.checkinchild;
import org.onechurch.childrenministry.sessions.application.AdmissionPolicy;
import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.sessions.domain.AttendanceSegment;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.repository.AttendanceRepository;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Optional;
import java.util.UUID;
public final class CheckInChildHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipantAccess participants;
    private final StaffScope scope;
    private final SessionRepository sessionRepository;
    private final AttendanceReader attendance;
    private final AttendanceRepository attendanceRepository;
    private final SessionOperations operations;
    private final AdmissionPolicy admission;
    public CheckInChildHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipantAccess participants, StaffScope scope, SessionRepository sessionRepository,
        AttendanceReader attendance, AttendanceRepository attendanceRepository, SessionOperations operations,
        AdmissionPolicy admission) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participants = participants;
        this.scope = scope;
        this.sessionRepository = sessionRepository;
        this.attendance = attendance;
        this.attendanceRepository = attendanceRepository;
        this.operations = operations;
        this.admission = admission;
    }
    public AttendanceView handle(CheckInChildCommand command) {
        authorization.requirePermission(command.actorId(), "children.station");
        return transactions.execute(() -> {
            participants.lockChild(command.childId());
            MinistrySession session = sessionRepository.ministrySessionForUpdate(command.sessionId())
                .orElseThrow(() -> ChildrenException.missing("MinistrySession"));
            scope.require(command.actorId(), session.id());
            Optional<Attendance> previous = attendance.arrival(command.actorId(), command.requestId());
            if (previous.isPresent()) {
                Attendance replay = previous.orElseThrow();
                if (!replay.childId().equals(command.childId()) || !attendance.segments(replay.id())
                    .stream()
                    .findFirst()
                    .map(segment -> segment.sessionId().equals(command.sessionId()))
                    .orElse(false)) {
                    throw ChildrenException.conflict("Arrival request ID was already used for another arrival.");
                }
                return AttendanceView.from(replay);
            }
            Instant now = clock.instant();
            session.requireArrivalWindow(now);
            operations.requireSpace(session, now);
            AdmissionPolicy.Evidence evidence = admission.require(session, command.childId(), now);
            Attendance value =
                new Attendance(UUID.randomUUID(), command.childId(), session.id(), now, command.actorId(),
                    command.requestId(), null, null, null, null, null, false, null, null, 0, null, 0, null, 0);
            attendanceRepository.insertAttendance(value);
            attendanceRepository.insertAttendanceSegment(new AttendanceSegment(UUID.randomUUID(), value.id(),
                session.id(), evidence.enrolmentId(), evidence.assignmentId(), evidence.consentId(), now, null));
            attendanceRepository.insertAttendanceEvent(
                new AttendanceEvent(UUID.randomUUID(), value.id(), command.actorId(), "ARRIVED", "", now));
            audit.record(command.actorId(), "child.checked.in", value.id(), "", now);
            return AttendanceView.from(value);
        });
    }
}

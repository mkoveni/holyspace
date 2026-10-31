package org.onechurch.childrenministry.sessions.application.command.transferchild;
import org.onechurch.childrenministry.sessions.application.AdmissionPolicy;
import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.sessions.domain.AttendanceSegment;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.repository.AttendanceRepository;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Comparator;
import java.util.UUID;
import java.util.stream.Stream;
public final class TransferChildHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipantAccess participants;
    private final SessionRepository sessionRepository;
    private final AttendanceReader attendance;
    private final AttendanceRepository attendanceRepository;
    private final SessionOperations operations;
    private final AdmissionPolicy admission;
    public TransferChildHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipantAccess participants, SessionRepository sessionRepository, AttendanceReader attendance,
        AttendanceRepository attendanceRepository, SessionOperations operations, AdmissionPolicy admission) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participants = participants;
        this.sessionRepository = sessionRepository;
        this.attendance = attendance;
        this.attendanceRepository = attendanceRepository;
        this.operations = operations;
        this.admission = admission;
    }
    public AttendanceView handle(TransferChildCommand command) {
        authorization.requirePermission(command.actorId(), "children.supervise");
        return transactions.execute(() -> {
            Attendance source = attendance.attendance(command.attendanceId())
                .orElseThrow(() -> ChildrenException.missing("Attendance"));
            participants.lockChild(source.childId());
            Stream.of(source.sessionId(), command.targetSessionId())
                .distinct()
                .sorted(Comparator.comparing(UUID::toString))
                .forEach(id -> sessionRepository.ministrySessionForUpdate(id)
                    .orElseThrow(() -> ChildrenException.missing("Session")));
            Attendance value = attendanceRepository.attendanceForUpdate(command.attendanceId())
                .orElseThrow(() -> ChildrenException.missing("Attendance"));
            if (!value.sessionId().equals(source.sessionId())) {
                throw ChildrenException.conflict("Attendance moved. Reload before transferring.");
            }
            MinistrySession target = sessionRepository.ministrySessionForUpdate(command.targetSessionId())
                .orElseThrow(() -> ChildrenException.missing("MinistrySession"));
            Instant now = clock.instant();
            target.requireArrivalWindow(now);
            operations.requireSpace(target, now);
            AdmissionPolicy.Evidence evidence = admission.require(target, value.childId(), now);
            String reason = ChildrenRules.text(command.reason(), 500);
            Attendance updated = value.transfer(target.id(), command.expectedVersion());
            attendanceRepository.closeSegment(value.id(), now);
            attendanceRepository.updateAttendance(updated);
            attendanceRepository.insertAttendanceSegment(new AttendanceSegment(UUID.randomUUID(), value.id(),
                target.id(), evidence.enrolmentId(), evidence.assignmentId(), evidence.consentId(), now, null));
            attendanceRepository.insertAttendanceEvent(
                new AttendanceEvent(UUID.randomUUID(), value.id(), command.actorId(), "TRANSFERRED", reason, now));
            audit.record(command.actorId(), "child.transferred", value.id(), reason, now);
            return AttendanceView.from(updated);
        });
    }
}

package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.PickupAuthorization;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.application.contract.PickupTokens;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
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
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.Locale;
import java.util.UUID;
public final class Handover {
    private final PermissionChecker authorization;
    private final ParticipantAccess participants;
    private final StaffScope scope;
    private final AttendanceReader attendance;
    private final AttendanceRepository repository;
    private final SessionRepository sessions;
    private final ParticipationReader participation;
    private final SafeguardingReader safeguarding;
    private final PickupTokens tokens;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ZoneId zone;
    public Handover(PermissionChecker authorization, ParticipantAccess participants, StaffScope scope,
        AttendanceReader attendance, AttendanceRepository repository, SessionRepository sessions,
        ParticipationReader participation, SafeguardingReader safeguarding, PickupTokens tokens, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ZoneId zone) {
        this.authorization = authorization;
        this.participants = participants;
        this.scope = scope;
        this.attendance = attendance;
        this.repository = repository;
        this.sessions = sessions;
        this.participation = participation;
        this.safeguarding = safeguarding;
        this.tokens = tokens;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.zone = zone;
    }
    public AttendanceView collect(UUID actorId, UUID attendanceId, long expectedVersion, UUID requestId,
        UUID collectorId, boolean identityVerified, String code, String exceptionReason) {
        authorization.requirePermission(actorId, exceptionReason == null ? "children.station" : "children.supervise");
        Decision decision = transactions.execute(() -> {
            Attendance source =
                attendance.attendance(attendanceId).orElseThrow(() -> ChildrenException.missing("Attendance"));
            participants.lockChild(source.childId(), collectorId);
            MinistrySession session = sessions.ministrySessionForUpdate(source.sessionId())
                .orElseThrow(() -> ChildrenException.missing("Session"));
            scope.require(actorId, session.id());
            Attendance value =
                repository.attendanceForUpdate(attendanceId).orElseThrow(() -> ChildrenException.missing("Attendance"));
            if (!session.id().equals(value.sessionId())) {
                throw ChildrenException.conflict("Attendance moved; reload it first.");
            }
            if (value.checkedOutAt() != null) {
                if (requestId.equals(value.checkoutRequestId()) && collectorId.equals(value.collectorId())
                    && actorId.equals(value.checkedOutBy())) {
                    return new Decision(AttendanceView.from(value), null);
                }
                throw ChildrenException.conflict("Child has already been collected.");
            }
            ChildrenRules.version(value.version(), expectedVersion);
            Instant now = clock.instant();
            if (!identityVerified) {
                throw ChildrenException.invalid("Confirm collector identity before handover.");
            }
            ChildrenRules.adult(participants.available(collectorId).birthDate(), LocalDate.now(clock.withZone(zone)));
            MinistryClass ministryClass =
                participation.ministryClass(session.classId()).orElseThrow(() -> ChildrenException.missing("Class"));
            PickupAuthorization grant =
                safeguarding.validAuthorizations(ministryClass.programmeId(), value.childId(), collectorId, now)
                    .stream()
                    .filter(item -> safeguarding.guardianAuthority(item.authorityId())
                        .filter(authority -> authority.covers(now, now))
                        .isPresent())
                    .findFirst()
                    .orElseThrow(() -> ChildrenException
                        .conflict("A current pickup authorization and guardian authority are required."));
            if (exceptionReason == null) {
                if (value.throttled(now)) {
                    return new Decision(null, new ChildrenException(ChildrenException.Kind.THROTTLED,
                        "Wait before trying another pickup code."));
                }
                boolean valid = value.labelHash() != null && value.labelExpiresAt() != null
                    && now.isBefore(value.labelExpiresAt()) && tokens.matches(code, value.labelHash());
                if (!valid) {
                    Attendance failed = value.failedAttempt(now);
                    repository.updateAttendance(failed);
                    audit.record(actorId, "pickup.label.rejected", value.id(), "", now);
                    return new Decision(null,
                        failed.throttled(now)
                            ? new ChildrenException(ChildrenException.Kind.THROTTLED,
                                "Wait before trying another pickup code.")
                            : ChildrenException
                                .invalid("Pickup code is invalid or expired. Reload attendance before retrying."));
                }
            } else {
                ChildrenRules.text(exceptionReason, 500);
            }
            Attendance updated =
                value.collect(collectorId, actorId, requestId, grant.id(), true, exceptionReason, expectedVersion, now);
            repository.updateAttendance(updated);
            repository.closeSegment(value.id(), now);
            String action = exceptionReason == null ? "COLLECTED" : "SUPERVISOR_COLLECTION";
            repository.insertAttendanceEvent(new AttendanceEvent(UUID.randomUUID(), value.id(), actorId, action,
                ChildrenRules.optional(exceptionReason, 500), now));
            audit.record(actorId, "attendance." + action.toLowerCase(Locale.ROOT), value.id(),
                ChildrenRules.optional(exceptionReason, 500), now);
            return new Decision(AttendanceView.from(updated), null);
        });
        if (decision.failure() != null) {
            throw decision.failure();
        }
        return decision.attendance();
    }
    private record Decision(AttendanceView attendance, ChildrenException failure) {}
}

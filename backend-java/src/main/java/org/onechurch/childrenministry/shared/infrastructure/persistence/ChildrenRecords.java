package org.onechurch.childrenministry.shared.infrastructure.persistence;

import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.Location;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.safeguarding.domain.Consent;
import org.onechurch.childrenministry.safeguarding.domain.ConsentPolicy;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.PickupAuthorization;
import org.onechurch.childrenministry.safeguarding.domain.SupportRecord;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.sessions.domain.AttendanceSegment;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.sessions.domain.StaffRole;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenAttendanceEventsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenAttendanceRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenAttendanceSegmentsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenClassAssignmentsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenClassesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenConsentPoliciesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenConsentsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenEnrolmentsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenGuardianAuthoritiesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenLocationsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenPickupAuthorizationsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenProgrammesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenRoomsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenSessionStaffRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenSessionsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenSupportRecordsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenWorkersRecord;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;
public final class ChildrenRecords {
    private ChildrenRecords() {
    }
    public static Instant instant(OffsetDateTime at) {
        return at == null ? null : at.toInstant();
    }
    public static OffsetDateTime time(Instant at) {
        return at == null ? null : at.atOffset(ZoneOffset.UTC);
    }
    public static Programme programme(ChildrenProgrammesRecord row) {
        return new Programme(row.getId(), row.getName(), row.getCurrentPolicyId(), row.getMinStaff(),
            row.getChildrenPerStaff(), row.getVersion(), instant(row.getCreatedAt()));
    }
    public static ConsentPolicy consentPolicy(ChildrenConsentPoliciesRecord row) {
        return new ConsentPolicy(row.getId(), row.getProgrammeId(), row.getLabel(), row.getDocumentReference(),
            row.getContentHash(), instant(row.getCreatedAt()));
    }
    public static MinistryClass ministryClass(ChildrenClassesRecord row) {
        return new MinistryClass(row.getId(), row.getProgrammeId(), row.getName(), row.getMinAgeMonths(),
            row.getMaxAgeMonths());
    }
    public static Enrolment enrolment(ChildrenEnrolmentsRecord row) {
        return new Enrolment(row.getId(), row.getProgrammeId(), row.getChildId(), row.getValidFrom(),
            row.getValidUntil(), instant(row.getEndedAt()), row.getVersion(), instant(row.getCreatedAt()));
    }
    public static ClassAssignment classAssignment(ChildrenClassAssignmentsRecord row) {
        return new ClassAssignment(row.getId(), row.getProgrammeId(), row.getClassId(), row.getChildId(),
            row.getEnrolmentId(), row.getValidFrom(), row.getValidUntil(), instant(row.getEndedAt()), row.getVersion());
    }
    public static GuardianAuthority guardianAuthority(ChildrenGuardianAuthoritiesRecord row) {
        return new GuardianAuthority(row.getId(), row.getChildId(), row.getGuardianId(), instant(row.getValidFrom()),
            instant(row.getValidUntil()), row.getEvidenceReference(), row.getVerifiedBy(), instant(row.getRevokedAt()),
            row.getRevocationReason(), row.getVersion());
    }
    public static Consent consent(ChildrenConsentsRecord row) {
        return new Consent(row.getId(), row.getProgrammeId(), row.getPolicyId(), row.getChildId(), row.getAuthorityId(),
            instant(row.getValidFrom()), instant(row.getValidUntil()), instant(row.getAcceptedAt()),
            row.getRecordedBy(), row.getEvidenceReference(), instant(row.getRevokedAt()), row.getRevocationReason(),
            row.getVersion());
    }
    public static PickupAuthorization pickupAuthorization(ChildrenPickupAuthorizationsRecord row) {
        return new PickupAuthorization(row.getId(), row.getProgrammeId(), row.getChildId(), row.getCollectorId(),
            row.getAuthorityId(), instant(row.getValidFrom()), instant(row.getValidUntil()), row.getEvidenceReference(),
            row.getRecordedBy(), instant(row.getRevokedAt()), row.getRevocationReason(), row.getVersion());
    }
    public static Location location(ChildrenLocationsRecord row) {
        return new Location(row.getId(), row.getName(), row.getTimeZone());
    }
    public static Room room(ChildrenRoomsRecord row) {
        return new Room(row.getId(), row.getLocationId(), row.getName(), row.getCapacity());
    }
    public static Worker worker(ChildrenWorkersRecord row) {
        return new Worker(row.getPersonId(), row.getAccountId(), instant(row.getVerifiedFrom()),
            instant(row.getScreenedUntil()), instant(row.getTrainedUntil()), row.getEvidenceReference(),
            row.getVerifiedBy(), instant(row.getRevokedAt()), row.getVersion());
    }
    public static MinistrySession ministrySession(ChildrenSessionsRecord row) {
        return new MinistrySession(row.getId(), row.getClassId(), row.getRoomId(), instant(row.getStartsAt()),
            instant(row.getEndsAt()), row.getSeatLimit(), SessionStatus.valueOf(row.getStatus()), row.getVersion());
    }
    public static SessionStaff sessionStaff(ChildrenSessionStaffRecord row) {
        return new SessionStaff(row.getSessionId(), row.getWorkerId(), StaffRole.valueOf(row.getRole()),
            row.getPresent());
    }
    public static Attendance attendance(ChildrenAttendanceRecord row) {
        return new Attendance(row.getId(), row.getChildId(), row.getSessionId(), instant(row.getArrivedAt()),
            row.getArrivedBy(), row.getArrivalRequestId(), instant(row.getCheckedOutAt()), row.getCheckedOutBy(),
            row.getCollectorId(), row.getCheckoutRequestId(), row.getAuthorizationId(), row.getIdentityVerified(),
            row.getExceptionReason(), row.getLabelHash(), row.getLabelVersion(), instant(row.getLabelExpiresAt()),
            row.getFailedAttempts(), instant(row.getAttemptsResetAt()), row.getVersion());
    }
    public static AttendanceSegment attendanceSegment(ChildrenAttendanceSegmentsRecord row) {
        return new AttendanceSegment(row.getId(), row.getAttendanceId(), row.getSessionId(), row.getEnrolmentId(),
            row.getAssignmentId(), row.getConsentId(), instant(row.getArrivedAt()), instant(row.getLeftAt()));
    }
    public static AttendanceEvent attendanceEvent(ChildrenAttendanceEventsRecord row) {
        return new AttendanceEvent(row.getId(), row.getAttendanceId(), row.getActorId(), row.getType(), row.getReason(),
            instant(row.getOccurredAt()));
    }
    public static SupportRecord supportRecord(ChildrenSupportRecordsRecord row) {
        return new SupportRecord(row.getChildId(), row.getAllergies(), row.getCareInstructions(),
            row.getRestrictedNotes(), row.getReviewOn(), row.getRetainUntil(), row.getVersion(),
            instant(row.getUpdatedAt()));
    }
}

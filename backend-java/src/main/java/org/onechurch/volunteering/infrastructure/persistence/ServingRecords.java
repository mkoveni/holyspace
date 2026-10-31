package org.onechurch.volunteering.infrastructure.persistence;

import org.onechurch.shared.infrastructure.jooq.tables.records.ServingAssignmentsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingAvailabilityExceptionsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingAvailabilityRulesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingCredentialTypesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingCredentialsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingInvitationsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingMembershipsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingRoleApprovalsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingShiftsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingTeamsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingVolunteersRecord;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.AvailabilityException;
import org.onechurch.volunteering.domain.AvailabilityRule;
import org.onechurch.volunteering.domain.Credential;
import org.onechurch.volunteering.domain.CredentialType;
import org.onechurch.volunteering.domain.Invitation;
import org.onechurch.volunteering.domain.RoleApproval;
import org.onechurch.volunteering.domain.Shift;
import org.onechurch.volunteering.domain.Team;
import org.onechurch.volunteering.domain.TeamMembership;
import org.onechurch.volunteering.domain.Volunteer;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;

final class ServingRecords {
    private ServingRecords() {
    }
    static Instant instant(OffsetDateTime value) {
        return value == null ? null : value.toInstant();
    }
    static OffsetDateTime time(Instant value) {
        return value == null ? null : value.atOffset(ZoneOffset.UTC);
    }
    static Volunteer volunteer(ServingVolunteersRecord r) {
        return new Volunteer(r.getId(), r.getPersonId(), r.getAccountId(), r.getStatus(), r.getAvailabilityRevision(),
            r.getVersion(), instant(r.getCreatedAt()), instant(r.getUpdatedAt()));
    }
    static Team team(ServingTeamsRecord r) {
        return new Team(r.getId(), r.getName(), r.getActive(), r.getVersion());
    }
    static TeamMembership teamMembership(ServingMembershipsRecord r) {
        return new TeamMembership(r.getId(), r.getTeamId(), r.getVolunteerId(), instant(r.getValidFrom()),
            instant(r.getValidUntil()), instant(r.getRevokedAt()));
    }
    static RoleApproval roleApproval(ServingRoleApprovalsRecord r) {
        return new RoleApproval(r.getId(), r.getRoleId(), r.getVolunteerId(), instant(r.getValidFrom()),
            instant(r.getValidUntil()), instant(r.getRevokedAt()));
    }
    static CredentialType credentialType(ServingCredentialTypesRecord r) {
        return new CredentialType(r.getId(), r.getCode(), r.getName());
    }
    static Credential credential(ServingCredentialsRecord r) {
        return new Credential(r.getId(), r.getVolunteerId(), r.getTypeId(), instant(r.getValidFrom()),
            instant(r.getValidUntil()), r.getEvidence(), r.getVerifiedBy(), instant(r.getVerifiedAt()),
            instant(r.getRevokedAt()));
    }
    static AvailabilityRule availabilityRule(ServingAvailabilityRulesRecord r) {
        return new AvailabilityRule(r.getId(), r.getVolunteerId(), r.getRevision(), r.getDayOfWeek(), r.getStartsAt(),
            r.getEndsAt(), r.getTimeZone(), r.getValidFrom(), r.getValidUntil());
    }
    static AvailabilityException availabilityException(ServingAvailabilityExceptionsRecord r) {
        return new AvailabilityException(r.getId(), r.getVolunteerId(), r.getRevision(), instant(r.getStartsAt()),
            instant(r.getEndsAt()), r.getAvailable());
    }
    static Shift shift(ServingShiftsRecord r) {
        return new Shift(r.getId(), r.getRequestId(), r.getRequestHash(), r.getTeamId(), r.getRoleId(),
            r.getSourceKind(), r.getSourceId(), r.getSourceRevision(), instant(r.getStartsAt()), instant(r.getEndsAt()),
            r.getPositions(), r.getStatus(), r.getVersion(), r.getScheduleRevision());
    }
    static Assignment assignment(ServingAssignmentsRecord r) {
        return new Assignment(r.getId(), r.getRequestId(), r.getRequestHash(), r.getShiftId(), r.getVolunteerId(),
            r.getStatus(), r.getVersion(), r.getCreatedBy(), instant(r.getCreatedAt()));
    }
    static Invitation invitation(ServingInvitationsRecord r) {
        return new Invitation(r.getId(), r.getAssignmentId(), r.getAssignmentVersion(), r.getShiftVersion(),
            instant(r.getExpiresAt()), r.getStatus(), r.getVersion(), instant(r.getRespondedAt()));
    }
}

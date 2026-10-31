package org.onechurch.shared.infrastructure;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_LOCATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PROGRAMMES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_EMAIL_DELIVERIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.BeforeEach;

import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.UUID;
public abstract class ChildrenIntegrationTestSupport extends PeopleIntegrationTestSupport {
    protected UUID child, guardian, collector, worker, programme, policy, group, authority, enrolment, assignment,
        grant, location, room, session, consent;
    protected Instant from, until, end;
    protected LocalDate today;
    @BeforeEach
    protected void resetChildren() throws Exception {
        db.truncate(COMMUNICATION_EMAIL_DELIVERIES).cascade().execute();
        db.truncate(CHILDREN_PROGRAMMES).cascade().execute();
        db.truncate(CHILDREN_LOCATIONS).cascade().execute();
        db.truncate(CHILDREN_AUDIT).execute();
        for (String code : new String[]{"children.read", "children.manage", "children.safeguarding", "children.station",
            "children.supervise", "children.support", "children.incidents.record", "children.incidents.manage",
            "children.communications.send", "children.communications.receipts"}) {
            UUID id = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, id).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, id)
                .execute();
        }
        today = LocalDate.now(ZoneId.of("Africa/Johannesburg"));
        from = Instant.now().truncatedTo(java.time.temporal.ChronoUnit.MILLIS).minusSeconds(86400);
        until = Instant.now().truncatedTo(java.time.temporal.ChronoUnit.MILLIS).plusSeconds(86400 * 365L);
        end = Instant.now().plusSeconds(3600);
        child = datedPerson("Child", today.minusYears(10));
        guardian = datedPerson("Guardian", today.minusYears(40));
        collector = datedPerson("Collector", today.minusYears(30));
        worker = datedPerson("Worker", today.minusYears(35));
        programme = create("/programmes", body("name", "Sunday children", "minStaff", 1, "childrenPerStaff", 2));
        policy = create("/consent-policies", body("programmeId", programme, "expectedVersion", 0, "label", "2026",
            "documentReference", "consent/2026", "contentHash", "a".repeat(64)));
        group = create("/classes",
            body("programmeId", programme, "name", "Juniors", "minAgeMonths", 60, "maxAgeMonths", 156));
        enrolment = enrol(child);
        assignment = assign(child, enrolment, group);
        authority = create("/guardian-authorities", body("childId", child, "guardianId", guardian, "validFrom", from,
            "validUntil", until, "evidenceReference", "verified/guardian"));
        consent = consent(child, authority, policy);
        grant = create("/pickup-authorizations",
            body("programmeId", programme, "childId", child, "collectorId", collector, "authorityId", authority,
                "validFrom", from, "validUntil", until, "evidenceReference", "signed/pickup"));
        location = create("/locations", body("name", "Main campus", "timeZone", "Africa/Johannesburg"));
        room = create("/rooms", body("locationId", location, "name", "Room A", "capacity", 10));
        ok("POST", "/workers", body("personId", worker, "accountId", adminId, "screenedUntil", until, "trainedUntil",
            until, "evidenceReference", "verified/clearance"));
        session = newSession(group, room, 2);
        open(session);
    }
    protected UUID datedPerson(String name, LocalDate dob) throws Exception {
        return created(request("POST", "/api/people", admin,
            body("person", body("firstName", name, "lastName", "Mabasa", "dateOfBirth", dob))));
    }
    protected UUID create(String path, Object body) throws Exception {
        return created(request("POST", "/api/children" + path, admin, body));
    }
    protected Response call(String method, String path, Object body) throws Exception {
        return request(method, "/api/children" + path, admin, body);
    }
    protected void ok(String method, String path, Object body) throws Exception {
        Response response = call(method, path, body);
        assertThat(response.status()).withFailMessage(response.body()).isEqualTo(204);
    }
    protected UUID enrol(UUID childId) throws Exception {
        return create("/enrolments", body("programmeId", programme, "childId", childId, "validFrom", today.minusDays(1),
            "validUntil", today.plusYears(1)));
    }
    protected UUID assign(UUID childId, UUID enrolled, UUID classId) throws Exception {
        return create("/class-assignments", body("enrolmentId", enrolled, "classId", classId, "validFrom",
            today.minusDays(1), "validUntil", today.plusYears(1)));
    }
    protected UUID consent(UUID childId, UUID authorityId, UUID policyId) throws Exception {
        return create("/consents",
            body("childId", childId, "programmeId", programme, "policyId", policyId, "authorityId", authorityId,
                "validFrom", from, "validUntil", until, "acceptedAt", Instant.now(), "evidenceReference",
                "signed/consent"));
    }
    protected UUID newSession(UUID classId, UUID roomId, int seats) throws Exception {
        return create("/sessions", body("classId", classId, "roomId", roomId, "startsAt",
            Instant.now().minusSeconds(300), "endsAt", end, "seatLimit", seats));
    }
    protected void open(UUID sessionId) throws Exception {
        ok("PUT", "/sessions/staff",
            body("sessionId", sessionId, "expectedVersion", 0, "workerId", worker, "role", "LEADER", "present", true));
        ok("PUT", "/sessions/status",
            body("sessionId", sessionId, "expectedVersion", 1, "status", "OPEN", "reason", "Staff ready"));
    }
    protected Response arrive(UUID childId, UUID sessionId, UUID requestId) throws Exception {
        return call("POST", "/attendance", body("childId", childId, "sessionId", sessionId, "requestId", requestId));
    }
    protected Response arrival() throws Exception {
        Response response = arrive(child, session, UUID.randomUUID());
        assertThat(response.status()).withFailMessage(response.body()).isEqualTo(200);
        return response;
    }
    protected UUID attendanceId(Response response) {
        return UUID.fromString((String) response.data().get("id"));
    }
    protected Response label(UUID attendanceId, long version, String reason) throws Exception {
        return call("POST", "/attendance/labels",
            body("attendanceId", attendanceId, "expectedVersion", version, "replacementReason", reason));
    }
    protected Response checkout(UUID attendanceId, long version, String code, boolean verified, UUID requestId)
        throws Exception {
        return call("POST", "/attendance/checkout", body("attendanceId", attendanceId, "expectedVersion", version,
            "requestId", requestId, "collectorId", collector, "identityVerified", verified, "pickupCode", code));
    }
    protected static Map<String, Object> body(Object... values) {
        Map<String, Object> result = new LinkedHashMap<>();
        for (int i = 0; i < values.length; i += 2) {
            result.put((String) values[i], values[i + 1]);
        }
        return result;
    }
}

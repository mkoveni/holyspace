package org.onechurch.communities.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_EMAIL_DELIVERIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_LEADERSHIP;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_MEETINGS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_MEMBERSHIPS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_NOTICES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_ROSTER;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLES;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ROLES;

import org.junit.jupiter.api.Test;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communities.infrastructure.CommunityDeliveryEligibility;
import org.onechurch.shared.infrastructure.CommunityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.annotation.Qualifier;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
class CommunityApiIntegrationTest extends CommunityIntegrationTestSupport {
    @Autowired CommunityDeliveryEligibility eligibility;
    @Autowired
    @Qualifier("communicationDeliveryStore") DeliveryReader deliveries;
    private String path(UUID id) {
        return COMMUNITIES_ROOT + "/" + id;
    }
    @Test
    void definitionsHaveDistinctKindsAndRelationalParentage() throws Exception {
        UUID ministry = community("MINISTRY", "Care"), key = UUID.randomUUID();
        Map<String, Object> body =
            Map.of("requestId", key, "kind", "GROUP", "name", "Home group", "parentId", ministry);
        UUID group = created(request("POST", COMMUNITIES_ROOT, admin, body));
        assertThat(created(request("POST", COMMUNITIES_ROOT, admin, body))).isEqualTo(group);
        assertThat(request("GET", COMMUNITIES_ROOT + "?kind=GROUP&search=Home", admin, null).data().get("total"))
            .isEqualTo(1);
        assertThat(request("POST", COMMUNITIES_ROOT, admin,
            Map.of("requestId", UUID.randomUUID(), "kind", "MINISTRY", "name", "Bad parent", "parentId", ministry))
            .status()).isEqualTo(400);
        assertThat(request("PUT", path(group), admin, Map.of("expectedVersion", 0, "name", "Updated", "description", "",
            "campus", "Central", "active", true, "reason", "Renamed group")).status()).isEqualTo(204);
        assertThat(request("PUT", path(group), admin, Map.of("expectedVersion", 0, "name", "Stale", "description", "",
            "campus", "", "active", true, "reason", "Old screen")).status()).isEqualTo(409);
    }
    @Test
    void membershipsEndImmediatelyAndRejoiningPreservesSeparatePeriods() throws Exception {
        UUID group = community("GROUP", "Home"), person = person("Solly"), member = join(group, person);
        assertThat(
            request("POST", path(group) + "/members", admin, Map.of("requestId", UUID.randomUUID(), "personId", person))
                .status())
            .isEqualTo(409);
        assertThat(request("POST", COMMUNITIES_ROOT + "/memberships/" + member + "/leave", admin,
            Map.of("expectedVersion", 0, "reason", "Moved to another group")).status()).isEqualTo(204);
        UUID rejoined = join(group, person);
        assertThat(rejoined).isNotEqualTo(member);
        assertThat(db.fetchCount(COMMUNITY_MEMBERSHIPS)).isEqualTo(2);
        assertThat(request("GET", path(group) + "/members?ended=true", admin, null).body()).contains("Solly Mabasa")
            .doesNotContain("password");
    }
    @Test
    void concurrentJoiningCannotDuplicateMembershipOrAudit() throws Exception {
        UUID group = community("GROUP", "Home"), person = person("Concurrent");
        CompletableFuture<Response> one = CompletableFuture.supplyAsync(() -> joinResponse(group, person)),
            two = CompletableFuture.supplyAsync(() -> joinResponse(group, person));
        assertThat(java.util.List.of(one.join().status(), two.join().status())).containsExactlyInAnyOrder(201, 409);
        assertThat(db.fetchCount(COMMUNITY_MEMBERSHIPS)).isEqualTo(1);
    }
    @Test
    void currentLeadershipScopesAccessAndEndingMembershipRevokesIt() throws Exception {
        UUID group = community("GROUP", "Home"), other = community("GROUP", "Other"), person = person("Leader"),
            member = join(group, person);
        UUID user = createUser("leader", "leader@example.org", "Group Leader"), role = UUID.randomUUID();
        db.insertInto(ROLES).set(ROLES.ID, role).set(ROLES.NAME, "group-leader").execute();
        db.insertInto(USER_ROLES).set(USER_ROLES.USER_ID, user).set(USER_ROLES.ROLE_ID, role).execute();
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("ministries.lead"))
            .fetchOne(0, UUID.class);
        db.insertInto(ROLE_PERMISSIONS)
            .set(ROLE_PERMISSIONS.ROLE_ID, role)
            .set(ROLE_PERMISSIONS.PERMISSION_ID, permission)
            .execute();
        created(request("POST", COMMUNITIES_ROOT + "/memberships/" + member + "/leadership", admin,
            Map.of("requestId", UUID.randomUUID(), "expectedVersion", 0, "role", "LEADER", "accountId", user, "reason",
                "Verified login identity")));
        String token = login("leader", PASSWORD);
        assertThat(request("GET", COMMUNITIES_ROOT, token, null).body()).contains(group.toString())
            .doesNotContain(other.toString());
        assertThat(request("GET", path(group) + "/members", token, null).status()).isEqualTo(200);
        assertThat(request("GET", path(other) + "/members", token, null).status()).isEqualTo(403);
        assertThat(request("POST", path(group) + "/members", token,
            Map.of("requestId", UUID.randomUUID(), "personId", person("Other person"))).status()).isEqualTo(403);
        assertThat(request("POST", COMMUNITIES_ROOT + "/memberships/" + member + "/leave", admin,
            Map.of("expectedVersion", 0, "reason", "Leadership and membership ended")).status()).isEqualTo(204);
        assertThat(request("GET", path(group) + "/members", token, null).status()).isEqualTo(403);
        assertThat(db.selectFrom(COMMUNITY_LEADERSHIP).fetchOne().getEndedAt()).isNotNull();
    }
    @Test
    void meetingPreparationFreezesMembershipAndGuestsDoNotJoinTheGroup() throws Exception {
        UUID group = community("GROUP", "Home"), person = person("Expected"), member = join(group, person),
            meeting = groupMeeting(group);
        assertThat(request("GET", path(group) + "/meetings", admin, null).status()).isEqualTo(200);
        assertThat(db.fetchCount(COMMUNITY_ROSTER)).isZero();
        assertThat(prepare(meeting, 0).status()).isEqualTo(204);
        assertThat(db.fetchCount(COMMUNITY_ROSTER)).isEqualTo(1);
        assertThat(request("POST", COMMUNITIES_ROOT + "/memberships/" + member + "/leave", admin,
            Map.of("expectedVersion", 0, "reason", "Membership ended after roster preparation")).status())
            .isEqualTo(204);
        assertThat(request("GET", COMMUNITIES_ROOT + "/meetings/" + meeting + "/roster", admin, null).body())
            .contains("Expected Mabasa");
        UUID guest = person("Guest");
        assertThat(request("POST", COMMUNITIES_ROOT + "/meetings/" + meeting + "/guests", admin,
            Map.of("expectedVersion", 1, "personId", guest, "reason", "Welcomed a guest")).status()).isEqualTo(204);
        assertThat(db.fetchCount(COMMUNITY_MEMBERSHIPS, COMMUNITY_MEMBERSHIPS.PERSON_ID.eq(guest))).isZero();
        assertThat(db.fetchCount(COMMUNITY_ROSTER)).isEqualTo(2);
        assertThat(mark(rosterRow(meeting, person), 0, "PRESENT").status()).isEqualTo(204);
    }
    @Test
    void correctionsRequireVersionsAndReasonsAndReportsKeepUnrecordedDistinct() throws Exception {
        UUID group = community("GROUP", "Home"), person = person("Attendee");
        join(group, person);
        join(group, person("Not marked"));
        UUID meeting = groupMeeting(group);
        assertThat(prepare(meeting, 0).status()).isEqualTo(204);
        UUID row = rosterRow(meeting, person);
        assertThat(mark(row, 0, "PRESENT").status()).isEqualTo(204);
        assertThat(mark(row, 0, "ABSENT").status()).isEqualTo(409);
        assertThat(request("PUT", COMMUNITIES_ROOT + "/roster/" + row, admin,
            Map.of("expectedVersion", 1, "status", "ABSENT", "reason", " ")).status()).isEqualTo(400);
        assertThat(mark(row, 1, "EXCUSED").status()).isEqualTo(204);
        Response reporting = request("GET", path(group) + "/meetings", admin, null);
        Map<?, ?> counts =
            (Map<?, ?>) ((Map<?, ?>) ((java.util.List<?>) reporting.data().get("meetings")).getFirst()).get("counts");
        assertThat(counts.get("excused")).isEqualTo(1);
        assertThat(counts.get("unrecorded")).isEqualTo(1);
        assertThat(counts.get("absent")).isEqualTo(0);
        assertThat(request("GET", path(group) + "/history", admin, null).body()).contains("ATTENDANCE_PRESENT",
            "ATTENDANCE_EXCUSED", "Verified by group coordinator");
    }
    @Test
    void frozenGroupRostersPreventEventReschedulingAndCancellationPreservesEvidence() throws Exception {
        UUID group = community("GROUP", "Home"), person = person("Present");
        join(group, person);
        UUID meeting = groupMeeting(group);
        assertThat(prepare(meeting, 0).status()).isEqualTo(204);
        assertThat(mark(rosterRow(meeting, person), 0, "PRESENT").status()).isEqualTo(204);
        UUID occurrence = db.selectFrom(COMMUNITY_MEETINGS).fetchOne().getOccurrenceId();
        Map<String, Object> source =
            (Map<String, Object>) request("GET", ROOT + "/occurrences/" + occurrence, admin, null).data()
                .get("occurrence");
        assertThat(request("POST", ROOT + "/occurrences/preview", admin,
            Map.of("draft", change(source, "RESCHEDULE", "THIS", "2099-01-01", "09:00", venue))).status())
            .isEqualTo(409);
        assertThat(
            apply(change(source, "CANCEL", "THIS", source.get("originDate").toString(), "09:00", venue)).status())
            .isEqualTo(204);
        assertThat(db.selectFrom(COMMUNITY_ROSTER).fetchOne().getStatus()).isEqualTo("PRESENT");
        assertThat(mark(rosterRow(meeting, person), 1, "EXCUSED").status()).isEqualTo(204);
    }
    @Test
    void closingKeepsUnrecordedPeopleAndAllowsReasonedCorrections() throws Exception {
        UUID group = community("GROUP", "Home"), person = person("Attendee");
        join(group, person);
        UUID meeting = groupMeeting(group);
        assertThat(prepare(meeting, 0).status()).isEqualTo(204);
        UUID row = rosterRow(meeting, person);
        assertThat(mark(row, 0, "PRESENT").status()).isEqualTo(204);
        Map<String, Object> close = Map.of("expectedVersion", 1, "status", "CLOSED", "reason", "Reviewed attendance");
        assertThat(request("POST", COMMUNITIES_ROOT + "/meetings/" + meeting + "/finish", admin, close).status())
            .isEqualTo(409);
        UUID occurrence = db.selectFrom(COMMUNITY_MEETINGS).fetchOne().getOccurrenceId();
        db.update(EVENT_OCCURRENCES)
            .set(EVENT_OCCURRENCES.STARTS_AT, OffsetDateTime.now().minusHours(2))
            .set(EVENT_OCCURRENCES.ENDS_AT, OffsetDateTime.now().minusHours(1))
            .where(EVENT_OCCURRENCES.ID.eq(occurrence))
            .execute();
        assertThat(request("POST", COMMUNITIES_ROOT + "/meetings/" + meeting + "/finish", admin, close).status())
            .isEqualTo(204);
        assertThat(mark(row, 1, "EXCUSED").status()).isEqualTo(204);
    }
    @Test
    void followupsAreScopedVersionedAndKeepCompletionHistory() throws Exception {
        UUID group = community("GROUP", "Home"), person = person("Follow up");
        join(group, person);
        UUID id = created(request("POST", path(group) + "/follow-ups", admin, Map.of("requestId", UUID.randomUUID(),
            "personId", person, "note", "Call after missed gathering", "dueOn", "2026-11-01")));
        assertThat(request("GET", path(group) + "/follow-ups", admin, null).body()).contains("Follow up Mabasa",
            "Call after missed gathering");
        assertThat(request("POST", COMMUNITIES_ROOT + "/follow-ups/" + id + "/complete", admin,
            Map.of("expectedVersion", 0, "reason", "Spoke with member")).status()).isEqualTo(204);
        assertThat(request("POST", COMMUNITIES_ROOT + "/follow-ups/" + id + "/complete", admin,
            Map.of("expectedVersion", 0, "reason", "Stale completion")).status()).isEqualTo(409);
        assertThat(request("GET", path(group) + "/follow-ups?completed=true", admin, null).body()).contains("DONE");
    }
    @Test
    void reviewedNoticesAreFrozenAndMembershipRevocationBlocksDispatch() throws Exception {
        UUID group = community("GROUP", "Home"), person = person("Recipient"), member = join(group, person),
            contact = created(request("POST", "/api/people/" + person + "/contacts", admin,
                Map.of("expectedVersion", 0, "kind", "EMAIL", "value", "member@example.org", "primary", true)));
        Map<String, Object> draft = Map.of("contactId", contact, "message", "Welcome <b>friend</b>");
        Response review = request("POST", COMMUNITIES_ROOT + "/memberships/" + member + "/notice-review", admin, draft);
        assertThat(review.status()).withFailMessage(review.body()).isEqualTo(200);
        assertThat(review.body()).contains("&lt;b&gt;");
        UUID key = UUID.randomUUID();
        Map<String, Object> approval =
            Map.of("requestId", key, "contactId", contact, "message", "Welcome <b>friend</b>", "reviewHash",
                review.data().get("hash"), "evidence", "Recipient confirmed own address and permission");
        UUID delivery =
            created(request("POST", COMMUNITIES_ROOT + "/memberships/" + member + "/notices", admin, approval));
        assertThat(created(request("POST", COMMUNITIES_ROOT + "/memberships/" + member + "/notices", admin, approval)))
            .isEqualTo(delivery);
        assertThat(eligibility.eligible(deliveries.delivery(delivery).orElseThrow(), Instant.now())).isTrue();
        assertThat(request("POST", COMMUNITIES_ROOT + "/memberships/" + member + "/leave", admin,
            Map.of("expectedVersion", 0, "reason", "Membership ended")).status()).isEqualTo(204);
        assertThat(eligibility.eligible(deliveries.delivery(delivery).orElseThrow(), Instant.now())).isFalse();
        assertThat(db.fetchCount(COMMUNITY_NOTICES)).isEqualTo(1);
        assertThat(db.fetchCount(COMMUNICATION_EMAIL_DELIVERIES)).isEqualTo(1);
        Response tracking = request("GET", path(group) + "/deliveries", admin, null);
        assertThat(tracking.status()).withFailMessage(tracking.body()).isEqualTo(200);
        assertThat(tracking.body()).contains("QUEUED").doesNotContain("member@example.org", "Welcome");
    }
    @Test
    void archivedCommunitiesRejectNewParticipationAndQueriesAreBounded() throws Exception {
        UUID group = community("GROUP", "Home"), person = person("Archived");
        assertThat(request("PUT", path(group), admin, Map.of("expectedVersion", 0, "name", "Home", "description", "",
            "campus", "", "active", false, "reason", "Group retired")).status()).isEqualTo(204);
        assertThat(
            request("POST", path(group) + "/members", admin, Map.of("requestId", UUID.randomUUID(), "personId", person))
                .status())
            .isEqualTo(409);
        assertThat(request("GET", COMMUNITIES_ROOT + "?limit=101", admin, null).status()).isEqualTo(400);
        assertThat(request("GET", COMMUNITIES_ROOT, null, null).status()).isEqualTo(401);
    }

    @Test
    void directoryReadersGetMetadataWithoutRosterAccessAndPeopleLinksStayPaged() throws Exception {
        UUID group = community("GROUP", "Directory visible"), person = person("Member link");
        join(group, person);
        String reader = readerToken();
        UUID role = db.select(ROLES.ID).from(ROLES).where(ROLES.NAME.eq("people-reader")).fetchOne(0, UUID.class);
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("ministries.read"))
            .fetchOne(0, UUID.class);
        db.insertInto(ROLE_PERMISSIONS)
            .set(ROLE_PERMISSIONS.ROLE_ID, role)
            .set(ROLE_PERMISSIONS.PERMISSION_ID, permission)
            .execute();
        Response detail = request("GET", path(group), reader, null);
        assertThat(detail.status()).withFailMessage(detail.body()).isEqualTo(200);
        assertThat(detail.data().get("canOperate")).isEqualTo(false);
        assertThat(request("GET", path(group) + "/members", reader, null).status()).isEqualTo(403);
        Response links = request("GET", COMMUNITIES_ROOT + "/people/" + person, reader, null);
        assertThat(links.status()).withFailMessage(links.body()).isEqualTo(200);
        assertThat(links.body()).contains("Directory visible").doesNotContain("email", "roster", "care");
        assertThat(request("GET", COMMUNITIES_ROOT + "/people/" + person + "?limit=101", reader, null).status())
            .isEqualTo(400);
    }
    @Test
    void loginBindingNeedsLiveIdentityManagementPermission() throws Exception {
        UUID group = community("GROUP", "Scoped identity"), member = join(group, person("Appointee"));
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("identity.manage"))
            .fetchOne(0, UUID.class);
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.ROLE_ID.eq(ADMIN_ROLE))
            .and(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission))
            .execute();
        Response denied = request("POST", COMMUNITIES_ROOT + "/memberships/" + member + "/leadership", admin,
            Map.of("requestId", UUID.randomUUID(), "expectedVersion", 0, "role", "LEADER", "accountId", adminId,
                "reason", "Verified identity"));
        assertThat(denied.status()).isEqualTo(403);
        assertThat(db.fetchCount(COMMUNITY_LEADERSHIP)).isZero();
    }
    @Test
    void databaseConstraintsRejectOverlappingMembershipEvenOutsideTheApplication() throws Exception {
        UUID group = community("GROUP", "Period protection"), person = person("Period"), member = join(group, person);
        OffsetDateTime started =
            db.selectFrom(COMMUNITY_MEMBERSHIPS).where(COMMUNITY_MEMBERSHIPS.ID.eq(member)).fetchOne().getStartedAt();
        org.assertj.core.api.Assertions
            .assertThatThrownBy(() -> db.insertInto(COMMUNITY_MEMBERSHIPS)
                .set(COMMUNITY_MEMBERSHIPS.ID, UUID.randomUUID())
                .set(COMMUNITY_MEMBERSHIPS.COMMUNITY_ID, group)
                .set(COMMUNITY_MEMBERSHIPS.PERSON_ID, person)
                .set(COMMUNITY_MEMBERSHIPS.STARTED_AT, started.plusSeconds(1))
                .execute())
            .isInstanceOf(org.springframework.dao.DataIntegrityViolationException.class)
            .hasMessageContaining("community_membership_period_excl");
        assertThat(db.fetchCount(COMMUNITY_MEMBERSHIPS)).isEqualTo(1);
    }
    private Response joinResponse(UUID group, UUID person) {
        try {
            return request("POST", path(group) + "/members", admin,
                Map.of("requestId", UUID.randomUUID(), "personId", person));
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }
}

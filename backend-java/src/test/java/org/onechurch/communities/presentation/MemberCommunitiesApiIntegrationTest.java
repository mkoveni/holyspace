package org.onechurch.communities.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_LEADERSHIP;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_MEETINGS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_MEMBERSHIPS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_ROSTER;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.CommunityIntegrationTestSupport;

import java.time.OffsetDateTime;
import java.util.Map;
import java.util.UUID;
class MemberCommunitiesApiIntegrationTest extends CommunityIntegrationTestSupport {
    private UUID account, person;
    private String member;
    @BeforeEach
    void member() throws Exception {
        account = createUser("member", "member@example.org", "Member");
        member = login("member", PASSWORD);
        person = person("Member");
    }
    private void link(boolean active, long version) throws Exception {
        assertThat(request("PUT", "/api/member-access/" + account, admin, Map.of("personId", person, "active", active,
            "expectedVersion", version, "evidence", "Identity verified in person")).status()).isEqualTo(204);
    }
    private String own(UUID id) {
        return "/api/member/communities/" + id;
    }
    private String meeting(UUID id) {
        return "/api/member/group-meetings/" + id;
    }
    @Test
    void verifiedMembershipDefinesScopeWithoutStaffPermissions() throws Exception {
        UUID group = community("GROUP", "Home group"), ministry = community("MINISTRY", "Welcome ministry"),
            hidden = community("GROUP", "Private leaders");
        join(group, person);
        join(ministry, person);
        assertThat(request("GET", "/api/member/communities", null, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/member/communities", member, null).body()).contains("\"verified\":false")
            .doesNotContain("Home group");
        link(true, 0);
        Response list = request("GET", "/api/member/communities?limit=1", member, null);
        assertThat(list.status()).isEqualTo(200);
        assertThat(list.data().get("hasMore")).isEqualTo(true);
        assertThat(request("GET", "/api/member/communities?kind=GROUP", member, null).body()).contains("Home group")
            .doesNotContain("Welcome ministry", "Private leaders");
        assertThat(request("GET", own(hidden), member, null).status()).isEqualTo(404);
        assertThat(request("GET", COMMUNITIES_ROOT + "/" + group + "/members", member, null).status()).isEqualTo(403);
        assertThat(request("GET", "/api/member/communities?limit=101", member, null).status()).isEqualTo(400);
        assertThat(request("GET", "/api/member/communities?kind=DEPARTMENT", member, null).status()).isEqualTo(400);
    }
    @Test
    void parentNamesDoNotGrantMembershipAndLeaderProjectionIsMinimal() throws Exception {
        UUID parent = community("MINISTRY", "Care ministry");
        UUID group = created(request("POST", COMMUNITIES_ROOT, admin,
            Map.of("requestId", UUID.randomUUID(), "kind", "GROUP", "name", "Care circle", "parentId", parent)));
        join(group, person);
        link(true, 0);
        UUID leaderPerson = person("Naledi"), leaderMembership = join(group, leaderPerson);
        UUID appointment = created(request("POST",
            COMMUNITIES_ROOT + "/memberships/" + leaderMembership + "/leadership", admin, Map.of("requestId",
                UUID.randomUUID(), "expectedVersion", 0, "role", "LEADER", "reason", "Appointed by ministry")));
        Response detail = request("GET", own(group), member, null);
        assertThat(detail.status()).isEqualTo(200);
        assertThat(detail.body()).contains("Care ministry", "Naledi Mabasa", "LEADER")
            .doesNotContain(leaderPerson.toString(), leaderMembership.toString(), appointment.toString(), "accountId",
                "personId", "contacts", "evidence");
        assertThat(request("GET", own(parent), member, null).status()).isEqualTo(404);
        assertThat(request("GET", own(group) + "?leaderLimit=0", member, null).status()).isEqualTo(400);
        db.update(COMMUNITY_LEADERSHIP)
            .set(COMMUNITY_LEADERSHIP.ENDED_AT, OffsetDateTime.now())
            .where(COMMUNITY_LEADERSHIP.ID.eq(appointment))
            .execute();
        assertThat(request("GET", own(group), member, null).body()).doesNotContain("Naledi Mabasa");
    }
    @Test
    void endedArchivedFutureAndRevokedMembershipsCannotBeRead() throws Exception {
        UUID group = community("GROUP", "Current group"), membership = join(group, person);
        link(true, 0);
        db.update(COMMUNITY_MEMBERSHIPS)
            .set(COMMUNITY_MEMBERSHIPS.ENDED_AT, OffsetDateTime.now())
            .where(COMMUNITY_MEMBERSHIPS.ID.eq(membership))
            .execute();
        assertThat(request("GET", own(group), member, null).status()).isEqualTo(404);
        assertThat(request("GET", "/api/member/communities", member, null).body()).doesNotContain(group.toString());
        UUID current = join(group, person);
        db.update(COMMUNITY_MEMBERSHIPS)
            .set(COMMUNITY_MEMBERSHIPS.STARTED_AT, OffsetDateTime.now().plusDays(1))
            .where(COMMUNITY_MEMBERSHIPS.ID.eq(current))
            .execute();
        assertThat(request("GET", own(group), member, null).status()).isEqualTo(404);
        db.update(COMMUNITY_MEMBERSHIPS)
            .set(COMMUNITY_MEMBERSHIPS.STARTED_AT, OffsetDateTime.now())
            .where(COMMUNITY_MEMBERSHIPS.ID.eq(current))
            .execute();
        db.update(COMMUNITIES).set(COMMUNITIES.ACTIVE, false).where(COMMUNITIES.ID.eq(group)).execute();
        assertThat(request("GET", own(group), member, null).status()).isEqualTo(404);
        db.update(COMMUNITIES).set(COMMUNITIES.ACTIVE, true).where(COMMUNITIES.ID.eq(group)).execute();
        link(false, 1);
        assertThat(request("GET", own(group), member, null).status()).isEqualTo(404);
        assertThat(request("GET", "/api/member/group-meetings", member, null).body()).contains("\"verified\":false");
    }
    @Test
    void meetingsExposeOnlyOwnAttendanceAndGetsNeverPrepareRosters() throws Exception {
        UUID group = community("GROUP", "Home group");
        join(group, person);
        join(group, person("Another"));
        link(true, 0);
        UUID meeting = groupMeeting(group);
        assertThat(request("GET", meeting(meeting), member, null).body()).contains("NOT_PREPARED")
            .doesNotContain("Another", "personId", "rosterId", "occurrenceId");
        assertThat(request("GET", "/api/member/group-meetings?communityId=" + group, member, null).body())
            .contains(meeting.toString());
        assertThat(db.fetchCount(COMMUNITY_ROSTER)).isZero();
        assertThat(prepare(meeting, 0).status()).isEqualTo(204);
        assertThat(request("GET", meeting(meeting), member, null).body()).contains("UNRECORDED")
            .doesNotContain("ABSENT");
        assertThat(mark(rosterRow(meeting, person), 0, "PRESENT").status()).isEqualTo(204);
        assertThat(request("GET", meeting(meeting), member, null).body()).contains("PRESENT").doesNotContain("Another");
        assertThat(request("GET", COMMUNITIES_ROOT + "/meetings/" + meeting + "/roster", member, null).status())
            .isEqualTo(403);
        assertThat(request("POST", meeting(meeting), member, Map.of("status", "PRESENT")).status()).isEqualTo(405);
    }
    @Test
    void otherMeetingsAndMeetingsPredatingMembershipRemainPrivate() throws Exception {
        UUID own = community("GROUP", "Own group"), other = community("GROUP", "Other group");
        join(own, person);
        link(true, 0);
        UUID meeting = groupMeeting(own);
        assertThat(request("GET", "/api/member/group-meetings?communityId=" + other, member, null).status())
            .isEqualTo(404);
        UUID otherMeeting = UUID.randomUUID();
        db.insertInto(COMMUNITY_MEETINGS)
            .set(COMMUNITY_MEETINGS.ID, otherMeeting)
            .set(COMMUNITY_MEETINGS.COMMUNITY_ID, other)
            .set(COMMUNITY_MEETINGS.OCCURRENCE_ID, UUID.randomUUID())
            .set(COMMUNITY_MEETINGS.SCHEDULED_AT, OffsetDateTime.now().plusDays(1))
            .set(COMMUNITY_MEETINGS.STATUS, "OPEN")
            .execute();
        assertThat(request("GET", meeting(otherMeeting), member, null).status()).isEqualTo(404);
        db.update(COMMUNITY_MEETINGS)
            .set(COMMUNITY_MEETINGS.SCHEDULED_AT, OffsetDateTime.now().minusDays(2))
            .where(COMMUNITY_MEETINGS.ID.eq(meeting))
            .execute();
        assertThat(request("GET", meeting(meeting), member, null).status()).isEqualTo(404);
    }
    @Test
    void cancelledSchedulesAndNotListedAttendanceStayDistinct() throws Exception {
        UUID group = community("GROUP", "Home group"), leader = person("Leader");
        join(group, leader);
        link(true, 0);
        UUID meeting = groupMeeting(group);
        assertThat(prepare(meeting, 0).status()).isEqualTo(204);
        join(group, person);
        assertThat(request("GET", meeting(meeting), member, null).body()).contains("NOT_LISTED")
            .doesNotContain("ABSENT");
        UUID occurrence =
            db.selectFrom(COMMUNITY_MEETINGS).where(COMMUNITY_MEETINGS.ID.eq(meeting)).fetchOne().getOccurrenceId();
        db.update(EVENT_OCCURRENCES)
            .set(EVENT_OCCURRENCES.STATUS, "CANCELLED")
            .where(EVENT_OCCURRENCES.ID.eq(occurrence))
            .execute();
        assertThat(request("GET", meeting(meeting), member, null).body()).contains("CANCELLED", "NOT_LISTED");
    }
}

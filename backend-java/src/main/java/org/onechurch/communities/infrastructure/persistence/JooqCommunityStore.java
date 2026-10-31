package org.onechurch.communities.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_FOLLOWUPS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_LEADERSHIP;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_MEETINGS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_MEMBERSHIPS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_NOTICES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_REQUESTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_ROSTER;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.communities.application.contract.CommunityWriter;
import org.onechurch.communities.domain.Community;
import org.onechurch.communities.domain.GroupMeeting;
import org.onechurch.communities.domain.Leadership;
import org.onechurch.communities.domain.Membership;
import org.onechurch.communities.domain.repository.CommunityRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.infrastructure.jooq.tables.records.CommunitiesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.CommunityLeadershipRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.CommunityMeetingsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.CommunityMembershipsRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.LocalDate;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.UUID;
import java.util.stream.Collectors;
@Repository
public class JooqCommunityStore implements CommunityReader, CommunityWriter, CommunityRepository {
    private final DSLContext db;
    private final PersonDirectory people;
    public JooqCommunityStore(DSLContext db, PersonDirectory people) {
        this.db = db;
        this.people = people;
    }
    private OffsetDateTime time(Instant at) {
        return at == null ? null : at.atOffset(ZoneOffset.UTC);
    }
    private Instant instant(OffsetDateTime at) {
        return at == null ? null : at.toInstant();
    }
    private Community community(CommunitiesRecord r) {
        return new Community(r.getId(), r.getKind(), r.getName(), r.getDescription(), r.getParentId(), r.getCampus(),
            r.getActive(), r.getVersion());
    }
    private Membership membership(CommunityMembershipsRecord r) {
        return new Membership(r.getId(), r.getCommunityId(), r.getPersonId(), instant(r.getStartedAt()),
            instant(r.getEndedAt()), r.getVersion());
    }
    private Leadership leadership(CommunityLeadershipRecord r) {
        return new Leadership(r.getId(), r.getMembershipId(), r.getCommunityId(), r.getAccountId(), r.getRole(),
            instant(r.getStartedAt()), instant(r.getEndedAt()), r.getVersion());
    }
    private GroupMeeting meeting(CommunityMeetingsRecord r) {
        return new GroupMeeting(r.getId(), r.getCommunityId(), r.getOccurrenceId(), instant(r.getScheduledAt()),
            r.getStatus(), r.getRosterFrozen(), r.getVersion());
    }
    private Map<UUID, String> names(List<UUID> ids) {
        return people.getAll(ids)
            .stream()
            .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
    }
    private Condition leadershipAt(Instant at) {
        return COMMUNITY_LEADERSHIP.STARTED_AT.le(time(at))
            .and(COMMUNITY_LEADERSHIP.ENDED_AT.isNull().or(COMMUNITY_LEADERSHIP.ENDED_AT.gt(time(at))))
            .and(COMMUNITY_MEMBERSHIPS.STARTED_AT.le(time(at)))
            .and(COMMUNITY_MEMBERSHIPS.ENDED_AT.isNull().or(COMMUNITY_MEMBERSHIPS.ENDED_AT.gt(time(at))));
    }
    private Condition scope(UUID account, Instant at) {
        return account == null ? DSL.noCondition()
            : COMMUNITIES.ACTIVE.isTrue()
                .and(COMMUNITIES.ID.in(db.select(COMMUNITY_LEADERSHIP.COMMUNITY_ID)
                    .from(COMMUNITY_LEADERSHIP)
                    .join(COMMUNITY_MEMBERSHIPS)
                    .on(COMMUNITY_MEMBERSHIPS.ID.eq(COMMUNITY_LEADERSHIP.MEMBERSHIP_ID))
                    .where(COMMUNITY_LEADERSHIP.ACCOUNT_ID.eq(account))
                    .and(leadershipAt(at))));
    }
    private Condition directoryWhere(String kind, String search, boolean archived, UUID account, Instant at) {
        return scope(account, at).and(kind == null ? DSL.noCondition() : COMMUNITIES.KIND.eq(kind))
            .and(archived ? DSL.noCondition() : COMMUNITIES.ACTIVE.isTrue())
            .and(search.isEmpty() ? DSL.noCondition() : COMMUNITIES.NAME.containsIgnoreCase(search));
    }
    public Optional<Community> community(UUID id) {
        return db.selectFrom(COMMUNITIES).where(COMMUNITIES.ID.eq(id)).fetchOptional(this::community);
    }
    public Optional<Community> lockCommunity(UUID id) {
        return db.selectFrom(COMMUNITIES).where(COMMUNITIES.ID.eq(id)).forUpdate().fetchOptional(this::community);
    }
    public List<Community> directory(String kind, String search, boolean archived, UUID account, int limit, int offset,
        Instant at) {
        return db.selectFrom(COMMUNITIES)
            .where(directoryWhere(kind, search, archived, account, at))
            .orderBy(COMMUNITIES.NAME, COMMUNITIES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::community);
    }
    public long directoryCount(String kind, String search, boolean archived, UUID account, Instant at) {
        return db.fetchCount(COMMUNITIES, directoryWhere(kind, search, archived, account, at));
    }
    public boolean leads(UUID community, UUID account, Instant at) {
        return db
            .fetchExists(db.selectOne().from(COMMUNITIES).where(COMMUNITIES.ID.eq(community)).and(scope(account, at)));
    }
    public Optional<Membership> membership(UUID id) {
        return db.selectFrom(COMMUNITY_MEMBERSHIPS)
            .where(COMMUNITY_MEMBERSHIPS.ID.eq(id))
            .fetchOptional(this::membership);
    }
    public Optional<Membership> currentMembership(UUID community, UUID person, Instant at) {
        return db.selectFrom(COMMUNITY_MEMBERSHIPS)
            .where(COMMUNITY_MEMBERSHIPS.COMMUNITY_ID.eq(community))
            .and(COMMUNITY_MEMBERSHIPS.PERSON_ID.eq(person))
            .and(COMMUNITY_MEMBERSHIPS.STARTED_AT.le(time(at)))
            .and(COMMUNITY_MEMBERSHIPS.ENDED_AT.isNull().or(COMMUNITY_MEMBERSHIPS.ENDED_AT.gt(time(at))))
            .fetchOptional(this::membership);
    }
    public List<Member> members(UUID community, boolean ended, int limit, int offset) {
        List<Membership> rows = db.selectFrom(COMMUNITY_MEMBERSHIPS)
            .where(COMMUNITY_MEMBERSHIPS.COMMUNITY_ID.eq(community))
            .and(ended ? DSL.noCondition() : COMMUNITY_MEMBERSHIPS.ENDED_AT.isNull())
            .orderBy(COMMUNITY_MEMBERSHIPS.STARTED_AT.desc(), COMMUNITY_MEMBERSHIPS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::membership);
        Map<UUID, String> names = names(rows.stream().map(Membership::personId).distinct().toList());
        return rows.stream().map(m -> new Member(m, names.getOrDefault(m.personId(), "Unavailable person"))).toList();
    }
    public long memberCount(UUID community, boolean ended) {
        return db.fetchCount(COMMUNITY_MEMBERSHIPS, COMMUNITY_MEMBERSHIPS.COMMUNITY_ID.eq(community)
            .and(ended ? DSL.noCondition() : COMMUNITY_MEMBERSHIPS.ENDED_AT.isNull()));
    }
    public List<Membership> membersAt(UUID community, Instant at, int limit) {
        return db.selectFrom(COMMUNITY_MEMBERSHIPS)
            .where(COMMUNITY_MEMBERSHIPS.COMMUNITY_ID.eq(community))
            .and(COMMUNITY_MEMBERSHIPS.STARTED_AT.le(time(at)))
            .and(COMMUNITY_MEMBERSHIPS.ENDED_AT.isNull().or(COMMUNITY_MEMBERSHIPS.ENDED_AT.gt(time(at))))
            .orderBy(COMMUNITY_MEMBERSHIPS.ID)
            .limit(limit)
            .fetch(this::membership);
    }
    public Optional<Leadership> activeLeadership(UUID membership) {
        return db.selectFrom(COMMUNITY_LEADERSHIP)
            .where(COMMUNITY_LEADERSHIP.MEMBERSHIP_ID.eq(membership))
            .and(COMMUNITY_LEADERSHIP.ENDED_AT.isNull())
            .fetchOptional(this::leadership);
    }
    public Optional<Leadership> leadership(UUID id) {
        return db.selectFrom(COMMUNITY_LEADERSHIP)
            .where(COMMUNITY_LEADERSHIP.ID.eq(id))
            .fetchOptional(this::leadership);
    }
    public List<Leadership> leadership(UUID id, int limit, int offset) {
        return db.selectFrom(COMMUNITY_LEADERSHIP)
            .where(COMMUNITY_LEADERSHIP.COMMUNITY_ID.eq(id))
            .orderBy(COMMUNITY_LEADERSHIP.STARTED_AT.desc(), COMMUNITY_LEADERSHIP.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::leadership);
    }
    public Optional<GroupMeeting> meeting(UUID id) {
        return db.selectFrom(COMMUNITY_MEETINGS).where(COMMUNITY_MEETINGS.ID.eq(id)).fetchOptional(this::meeting);
    }
    public Optional<GroupMeeting> lockMeeting(UUID id) {
        return db.selectFrom(COMMUNITY_MEETINGS)
            .where(COMMUNITY_MEETINGS.ID.eq(id))
            .forUpdate()
            .fetchOptional(this::meeting);
    }
    public List<GroupMeeting> meetings(UUID id, int limit, int offset) {
        return db.selectFrom(COMMUNITY_MEETINGS)
            .where(COMMUNITY_MEETINGS.COMMUNITY_ID.eq(id))
            .orderBy(COMMUNITY_MEETINGS.SCHEDULED_AT.desc(), COMMUNITY_MEETINGS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::meeting);
    }
    public long meetingCount(UUID id) {
        return db.fetchCount(COMMUNITY_MEETINGS, COMMUNITY_MEETINGS.COMMUNITY_ID.eq(id));
    }
    public List<Roster> roster(UUID id, int limit, int offset) {
        List<Roster> rows = db.selectFrom(COMMUNITY_ROSTER)
            .where(COMMUNITY_ROSTER.MEETING_ID.eq(id))
            .orderBy(COMMUNITY_ROSTER.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Roster(r.getId(), r.getMeetingId(), r.getPersonId(), r.getMembershipId(), r.getGuest(),
                r.getStatus(), r.getVersion(), ""));
        Map<UUID, String> names = names(rows.stream().map(Roster::personId).distinct().toList());
        return rows.stream()
            .map(r -> new Roster(r.id(), r.meetingId(), r.personId(), r.membershipId(), r.guest(), r.status(),
                r.version(), names.getOrDefault(r.personId(), "Unavailable person")))
            .toList();
    }
    public long rosterCount(UUID id) {
        return db.fetchCount(COMMUNITY_ROSTER, COMMUNITY_ROSTER.MEETING_ID.eq(id));
    }
    public Optional<Roster> rosterRow(UUID id) {
        return db.selectFrom(COMMUNITY_ROSTER)
            .where(COMMUNITY_ROSTER.ID.eq(id))
            .fetchOptional(r -> new Roster(r.getId(), r.getMeetingId(), r.getPersonId(), r.getMembershipId(),
                r.getGuest(), r.getStatus(), r.getVersion(), ""));
    }
    public List<History> history(UUID id, int limit, int offset) {
        return db.selectFrom(COMMUNITY_HISTORY)
            .where(COMMUNITY_HISTORY.COMMUNITY_ID.eq(id))
            .orderBy(COMMUNITY_HISTORY.OCCURRED_AT.desc(), COMMUNITY_HISTORY.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new History(r.getId(), r.getTargetId(), r.getAction(), r.getReason(), r.getActorId(),
                instant(r.getOccurredAt())));
    }
    public List<FollowUp> followups(UUID id, boolean completed, int limit, int offset) {
        List<FollowUp> rows = db.selectFrom(COMMUNITY_FOLLOWUPS)
            .where(COMMUNITY_FOLLOWUPS.COMMUNITY_ID.eq(id))
            .and(completed ? DSL.noCondition() : COMMUNITY_FOLLOWUPS.STATUS.eq("OPEN"))
            .orderBy(COMMUNITY_FOLLOWUPS.DUE_ON, COMMUNITY_FOLLOWUPS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new FollowUp(r.getId(), r.getCommunityId(), r.getPersonId(), "", r.getNote(), r.getDueOn(),
                r.getStatus(), r.getVersion()));
        Map<UUID, String> names = names(rows.stream().map(FollowUp::personId).distinct().toList());
        return rows.stream()
            .map(r -> new FollowUp(r.id(), r.communityId(), r.personId(),
                names.getOrDefault(r.personId(), "Unavailable person"), r.note(), r.dueOn(), r.status(), r.version()))
            .toList();
    }
    public Optional<FollowUp> followup(UUID id) {
        return db.selectFrom(COMMUNITY_FOLLOWUPS)
            .where(COMMUNITY_FOLLOWUPS.ID.eq(id))
            .fetchOptional(r -> new FollowUp(r.getId(), r.getCommunityId(), r.getPersonId(), "", r.getNote(),
                r.getDueOn(), r.getStatus(), r.getVersion()));
    }
    public List<Report> reports(List<UUID> meetings) {
        return db
            .select(COMMUNITY_MEETINGS.ID, DSL.count(COMMUNITY_ROSTER.ID),
                DSL.count().filterWhere(COMMUNITY_ROSTER.STATUS.eq("PRESENT")),
                DSL.count().filterWhere(COMMUNITY_ROSTER.STATUS.eq("ABSENT")),
                DSL.count().filterWhere(COMMUNITY_ROSTER.STATUS.eq("EXCUSED")),
                DSL.count().filterWhere(COMMUNITY_ROSTER.STATUS.eq("UNRECORDED")),
                DSL.count().filterWhere(COMMUNITY_ROSTER.GUEST.isTrue()))
            .from(COMMUNITY_MEETINGS)
            .leftJoin(COMMUNITY_ROSTER)
            .on(COMMUNITY_ROSTER.MEETING_ID.eq(COMMUNITY_MEETINGS.ID))
            .where(COMMUNITY_MEETINGS.ID.in(meetings))
            .groupBy(COMMUNITY_MEETINGS.ID)
            .fetch(r -> new Report(r.value1(), r.value2(), r.value3(), r.value4(), r.value5(), r.value6(), r.value7()));
    }
    public boolean recordedOccurrence(UUID id) {
        return db.fetchExists(db.selectOne()
            .from(COMMUNITY_MEETINGS)
            .where(COMMUNITY_MEETINGS.OCCURRENCE_ID.eq(id))
            .and(COMMUNITY_MEETINGS.ROSTER_FROZEN.isTrue()));
    }
    public Optional<Request> request(UUID id) {
        return db.selectFrom(COMMUNITY_REQUESTS)
            .where(COMMUNITY_REQUESTS.ID.eq(id))
            .fetchOptional(r -> new Request(r.getRequestHash().trim(), r.getTargetId()));
    }
    public List<Notice> notices(UUID id, int limit, int offset) {
        return db.selectFrom(COMMUNITY_NOTICES)
            .where(COMMUNITY_NOTICES.COMMUNITY_ID.eq(id))
            .orderBy(COMMUNITY_NOTICES.CREATED_AT.desc(), COMMUNITY_NOTICES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Notice(r.getId(), r.getCommunityId(), r.getMembershipId(), r.getContactId(),
                r.getRequestHash().trim(), r.getReviewHash().trim(), r.getCreatedBy(), r.getDeliveryId()));
    }
    public Optional<Notice> notice(UUID id) {
        return db.selectFrom(COMMUNITY_NOTICES)
            .where(COMMUNITY_NOTICES.ID.eq(id))
            .fetchOptional(r -> new Notice(r.getId(), r.getCommunityId(), r.getMembershipId(), r.getContactId(),
                r.getRequestHash().trim(), r.getReviewHash().trim(), r.getCreatedBy(), r.getDeliveryId()));
    }
    public void scheduleChanged(UUID occurrence, Instant startsAt) {
        db.update(COMMUNITY_MEETINGS)
            .set(COMMUNITY_MEETINGS.SCHEDULED_AT, time(startsAt))
            .where(COMMUNITY_MEETINGS.OCCURRENCE_ID.eq(occurrence))
            .execute();
    }
    public void insert(Community c) {
        db.insertInto(COMMUNITIES)
            .set(COMMUNITIES.ID, c.id())
            .set(COMMUNITIES.KIND, c.kind())
            .set(COMMUNITIES.NAME, c.name())
            .set(COMMUNITIES.DESCRIPTION, c.description())
            .set(COMMUNITIES.PARENT_ID, c.parentId())
            .set(COMMUNITIES.CAMPUS, c.campus())
            .set(COMMUNITIES.ACTIVE, c.active())
            .set(COMMUNITIES.VERSION, c.version())
            .execute();
    }
    public void save(Community c) {
        db.update(COMMUNITIES)
            .set(COMMUNITIES.NAME, c.name())
            .set(COMMUNITIES.DESCRIPTION, c.description())
            .set(COMMUNITIES.PARENT_ID, c.parentId())
            .set(COMMUNITIES.CAMPUS, c.campus())
            .set(COMMUNITIES.ACTIVE, c.active())
            .set(COMMUNITIES.VERSION, c.version())
            .where(COMMUNITIES.ID.eq(c.id()))
            .execute();
    }
    public void insert(Membership m) {
        db.insertInto(COMMUNITY_MEMBERSHIPS)
            .set(COMMUNITY_MEMBERSHIPS.ID, m.id())
            .set(COMMUNITY_MEMBERSHIPS.COMMUNITY_ID, m.communityId())
            .set(COMMUNITY_MEMBERSHIPS.PERSON_ID, m.personId())
            .set(COMMUNITY_MEMBERSHIPS.STARTED_AT, time(m.startedAt()))
            .set(COMMUNITY_MEMBERSHIPS.ENDED_AT, time(m.endedAt()))
            .set(COMMUNITY_MEMBERSHIPS.VERSION, m.version())
            .execute();
    }
    public void save(Membership m) {
        db.update(COMMUNITY_MEMBERSHIPS)
            .set(COMMUNITY_MEMBERSHIPS.ENDED_AT, time(m.endedAt()))
            .set(COMMUNITY_MEMBERSHIPS.VERSION, m.version())
            .where(COMMUNITY_MEMBERSHIPS.ID.eq(m.id()))
            .execute();
    }
    public void insert(Leadership l) {
        db.insertInto(COMMUNITY_LEADERSHIP)
            .set(COMMUNITY_LEADERSHIP.ID, l.id())
            .set(COMMUNITY_LEADERSHIP.MEMBERSHIP_ID, l.membershipId())
            .set(COMMUNITY_LEADERSHIP.COMMUNITY_ID, l.communityId())
            .set(COMMUNITY_LEADERSHIP.ACCOUNT_ID, l.accountId())
            .set(COMMUNITY_LEADERSHIP.ROLE, l.role())
            .set(COMMUNITY_LEADERSHIP.STARTED_AT, time(l.startedAt()))
            .set(COMMUNITY_LEADERSHIP.ENDED_AT, time(l.endedAt()))
            .set(COMMUNITY_LEADERSHIP.VERSION, l.version())
            .execute();
    }
    public void save(Leadership l) {
        db.update(COMMUNITY_LEADERSHIP)
            .set(COMMUNITY_LEADERSHIP.ENDED_AT, time(l.endedAt()))
            .set(COMMUNITY_LEADERSHIP.VERSION, l.version())
            .where(COMMUNITY_LEADERSHIP.ID.eq(l.id()))
            .execute();
    }
    public void insert(GroupMeeting m) {
        db.insertInto(COMMUNITY_MEETINGS)
            .set(COMMUNITY_MEETINGS.ID, m.id())
            .set(COMMUNITY_MEETINGS.COMMUNITY_ID, m.communityId())
            .set(COMMUNITY_MEETINGS.OCCURRENCE_ID, m.occurrenceId())
            .set(COMMUNITY_MEETINGS.SCHEDULED_AT, time(m.scheduledAt()))
            .set(COMMUNITY_MEETINGS.STATUS, m.status())
            .set(COMMUNITY_MEETINGS.ROSTER_FROZEN, m.rosterFrozen())
            .set(COMMUNITY_MEETINGS.VERSION, m.version())
            .execute();
    }
    public void save(GroupMeeting m) {
        db.update(COMMUNITY_MEETINGS)
            .set(COMMUNITY_MEETINGS.STATUS, m.status())
            .set(COMMUNITY_MEETINGS.ROSTER_FROZEN, m.rosterFrozen())
            .set(COMMUNITY_MEETINGS.VERSION, m.version())
            .where(COMMUNITY_MEETINGS.ID.eq(m.id()))
            .execute();
    }
    public void audit(UUID community, UUID target, String action, String reason, UUID actor, Instant at) {
        db.insertInto(COMMUNITY_HISTORY)
            .set(COMMUNITY_HISTORY.ID, UUID.randomUUID())
            .set(COMMUNITY_HISTORY.COMMUNITY_ID, community)
            .set(COMMUNITY_HISTORY.TARGET_ID, target)
            .set(COMMUNITY_HISTORY.ACTION, action)
            .set(COMMUNITY_HISTORY.REASON, reason)
            .set(COMMUNITY_HISTORY.ACTOR_ID, actor)
            .set(COMMUNITY_HISTORY.OCCURRED_AT, time(at))
            .execute();
    }
    public void request(UUID id, String hash, UUID target) {
        db.insertInto(COMMUNITY_REQUESTS)
            .set(COMMUNITY_REQUESTS.ID, id)
            .set(COMMUNITY_REQUESTS.REQUEST_HASH, hash)
            .set(COMMUNITY_REQUESTS.TARGET_ID, target)
            .execute();
    }
    public void roster(UUID meeting, UUID person, UUID membership, boolean guest) {
        db.insertInto(COMMUNITY_ROSTER)
            .set(COMMUNITY_ROSTER.ID, UUID.randomUUID())
            .set(COMMUNITY_ROSTER.MEETING_ID, meeting)
            .set(COMMUNITY_ROSTER.PERSON_ID, person)
            .set(COMMUNITY_ROSTER.MEMBERSHIP_ID, membership)
            .set(COMMUNITY_ROSTER.GUEST, guest)
            .set(COMMUNITY_ROSTER.STATUS, "UNRECORDED")
            .onConflict(COMMUNITY_ROSTER.MEETING_ID, COMMUNITY_ROSTER.PERSON_ID)
            .doNothing()
            .execute();
    }
    public void attendance(UUID row, String status) {
        db.update(COMMUNITY_ROSTER)
            .set(COMMUNITY_ROSTER.STATUS, status)
            .set(COMMUNITY_ROSTER.VERSION, COMMUNITY_ROSTER.VERSION.plus(1))
            .where(COMMUNITY_ROSTER.ID.eq(row))
            .execute();
    }
    public void followup(UUID id, UUID community, UUID person, String note, LocalDate due, UUID actor, Instant at) {
        db.insertInto(COMMUNITY_FOLLOWUPS)
            .set(COMMUNITY_FOLLOWUPS.ID, id)
            .set(COMMUNITY_FOLLOWUPS.COMMUNITY_ID, community)
            .set(COMMUNITY_FOLLOWUPS.PERSON_ID, person)
            .set(COMMUNITY_FOLLOWUPS.NOTE, note)
            .set(COMMUNITY_FOLLOWUPS.DUE_ON, due)
            .set(COMMUNITY_FOLLOWUPS.STATUS, "OPEN")
            .set(COMMUNITY_FOLLOWUPS.CREATED_BY, actor)
            .set(COMMUNITY_FOLLOWUPS.CREATED_AT, time(at))
            .execute();
    }
    public void completeFollowup(UUID id) {
        db.update(COMMUNITY_FOLLOWUPS)
            .set(COMMUNITY_FOLLOWUPS.STATUS, "DONE")
            .set(COMMUNITY_FOLLOWUPS.VERSION, COMMUNITY_FOLLOWUPS.VERSION.plus(1))
            .where(COMMUNITY_FOLLOWUPS.ID.eq(id))
            .execute();
    }
    public void notice(Notice n, String evidence, Instant at) {
        db.insertInto(COMMUNITY_NOTICES)
            .set(COMMUNITY_NOTICES.ID, n.id())
            .set(COMMUNITY_NOTICES.COMMUNITY_ID, n.communityId())
            .set(COMMUNITY_NOTICES.MEMBERSHIP_ID, n.membershipId())
            .set(COMMUNITY_NOTICES.CONTACT_ID, n.contactId())
            .set(COMMUNITY_NOTICES.REQUEST_HASH, n.requestHash())
            .set(COMMUNITY_NOTICES.REVIEW_HASH, n.reviewHash())
            .set(COMMUNITY_NOTICES.CREATED_BY, n.actorId())
            .set(COMMUNITY_NOTICES.CREATED_AT, time(at))
            .set(COMMUNITY_NOTICES.DELIVERY_ID, n.deliveryId())
            .set(COMMUNITY_NOTICES.EVIDENCE, evidence)
            .execute();
    }
}

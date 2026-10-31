package org.onechurch.communities.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_LEADERSHIP;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_MEETINGS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_MEMBERSHIPS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_ROSTER;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.Record;
import org.jooq.SelectConditionStep;
import org.jooq.impl.DSL;
import org.onechurch.communities.application.contract.MemberCommunityReader;
import org.onechurch.shared.infrastructure.jooq.tables.Communities;
import org.onechurch.shared.infrastructure.jooq.tables.CommunityLeadership;
import org.onechurch.shared.infrastructure.jooq.tables.CommunityMemberships;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqMemberCommunityStore implements MemberCommunityReader {
    private final DSLContext db;
    private final Communities parent = COMMUNITIES.as("parent_ministry");
    private final CommunityMemberships own = COMMUNITY_MEMBERSHIPS.as("own_membership");
    private final CommunityLeadership ownRole = COMMUNITY_LEADERSHIP.as("own_role");
    public JooqMemberCommunityStore(DSLContext db) {
        this.db = db;
    }
    private OffsetDateTime time(Instant at) {
        return at.atOffset(ZoneOffset.UTC);
    }
    private Condition current(UUID person, Instant at) {
        return own.PERSON_ID.eq(person)
            .and(own.STARTED_AT.le(time(at)))
            .and(own.ENDED_AT.isNull().or(own.ENDED_AT.gt(time(at))))
            .and(COMMUNITIES.ACTIVE.isTrue());
    }
    private SelectConditionStep<Record> communities(UUID person, Instant at, Condition condition) {
        return db.select(COMMUNITIES.fields())
            .select(own.STARTED_AT, ownRole.ROLE, parent.NAME)
            .from(COMMUNITIES)
            .join(own)
            .on(own.COMMUNITY_ID.eq(COMMUNITIES.ID))
            .leftJoin(ownRole)
            .on(ownRole.MEMBERSHIP_ID.eq(own.ID)
                .and(ownRole.STARTED_AT.le(time(at)))
                .and(ownRole.ENDED_AT.isNull().or(ownRole.ENDED_AT.gt(time(at)))))
            .leftJoin(parent)
            .on(parent.ID.eq(COMMUNITIES.PARENT_ID).and(parent.ACTIVE.isTrue()))
            .where(current(person, at).and(condition));
    }
    private MemberCommunity community(Record r) {
        return new MemberCommunity(r.get(COMMUNITIES.ID), r.get(COMMUNITIES.KIND), r.get(COMMUNITIES.NAME),
            r.get(COMMUNITIES.DESCRIPTION), r.get(COMMUNITIES.CAMPUS), r.get(parent.NAME),
            r.get(own.STARTED_AT).toInstant(), r.get(ownRole.ROLE) == null ? "MEMBER" : r.get(ownRole.ROLE));
    }
    public List<MemberCommunity> communities(UUID person, String kind, Instant at, int limit, int offset) {
        return communities(person, at, kind == null ? DSL.noCondition() : COMMUNITIES.KIND.eq(kind))
            .orderBy(COMMUNITIES.NAME, COMMUNITIES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::community);
    }
    public Optional<MemberCommunity> community(UUID person, UUID id, Instant at) {
        return communities(person, at, COMMUNITIES.ID.eq(id)).fetchOptional(this::community);
    }
    public List<Leader> leaders(UUID person, UUID community, Instant at, int limit, int offset) {
        return db.select(COMMUNITY_MEMBERSHIPS.PERSON_ID, COMMUNITY_LEADERSHIP.ROLE)
            .from(COMMUNITY_LEADERSHIP)
            .join(COMMUNITY_MEMBERSHIPS)
            .on(COMMUNITY_MEMBERSHIPS.ID.eq(COMMUNITY_LEADERSHIP.MEMBERSHIP_ID))
            .join(COMMUNITIES)
            .on(COMMUNITIES.ID.eq(COMMUNITY_LEADERSHIP.COMMUNITY_ID))
            .join(own)
            .on(own.COMMUNITY_ID.eq(COMMUNITIES.ID))
            .where(current(person, at).and(COMMUNITIES.ID.eq(community))
                .and(COMMUNITY_LEADERSHIP.STARTED_AT.le(time(at)))
                .and(COMMUNITY_LEADERSHIP.ENDED_AT.isNull().or(COMMUNITY_LEADERSHIP.ENDED_AT.gt(time(at))))
                .and(COMMUNITY_MEMBERSHIPS.STARTED_AT.le(time(at)))
                .and(COMMUNITY_MEMBERSHIPS.ENDED_AT.isNull().or(COMMUNITY_MEMBERSHIPS.ENDED_AT.gt(time(at)))))
            .orderBy(COMMUNITY_LEADERSHIP.ROLE, COMMUNITY_LEADERSHIP.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Leader(r.value1(), r.value2()));
    }
    private SelectConditionStep<Record> meetings(UUID person, Instant at, Condition condition) {
        return db.select(COMMUNITY_MEETINGS.fields())
            .select(COMMUNITIES.NAME, COMMUNITY_ROSTER.STATUS)
            .from(COMMUNITY_MEETINGS)
            .join(COMMUNITIES)
            .on(COMMUNITIES.ID.eq(COMMUNITY_MEETINGS.COMMUNITY_ID))
            .join(own)
            .on(own.COMMUNITY_ID.eq(COMMUNITIES.ID))
            .leftJoin(COMMUNITY_ROSTER)
            .on(COMMUNITY_ROSTER.MEETING_ID.eq(COMMUNITY_MEETINGS.ID).and(COMMUNITY_ROSTER.PERSON_ID.eq(person)))
            .where(current(person, at).and(condition)
                .and(COMMUNITY_MEETINGS.SCHEDULED_AT.ge(own.STARTED_AT).or(COMMUNITY_ROSTER.ID.isNotNull())));
    }
    private Meeting meeting(Record r) {
        String attendance = r.get(COMMUNITY_ROSTER.STATUS);
        if (attendance == null) {
            attendance = Boolean.TRUE.equals(r.get(COMMUNITY_MEETINGS.ROSTER_FROZEN)) ? "NOT_LISTED" : "NOT_PREPARED";
        }
        return new Meeting(r.get(COMMUNITY_MEETINGS.ID), r.get(COMMUNITY_MEETINGS.COMMUNITY_ID),
            r.get(COMMUNITIES.NAME), r.get(COMMUNITY_MEETINGS.OCCURRENCE_ID), r.get(COMMUNITY_MEETINGS.STATUS),
            attendance);
    }
    public List<Meeting> meetings(UUID person, UUID community, Instant from, Instant until, int limit, int offset) {
        return meetings(person, from,
            COMMUNITY_MEETINGS.SCHEDULED_AT.ge(time(from))
                .and(COMMUNITY_MEETINGS.SCHEDULED_AT.lt(time(until)))
                .and(community == null ? DSL.noCondition() : COMMUNITIES.ID.eq(community)))
            .orderBy(COMMUNITY_MEETINGS.SCHEDULED_AT, COMMUNITY_MEETINGS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::meeting);
    }
    public Optional<Meeting> meeting(UUID person, UUID id, Instant at) {
        return meetings(person, at, COMMUNITY_MEETINGS.ID.eq(id)).fetchOptional(this::meeting);
    }
}

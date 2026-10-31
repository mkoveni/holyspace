package org.onechurch.communities.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_MEMBERSHIPS;

import org.jooq.DSLContext;
import org.onechurch.communities.application.contract.CommunityParticipation;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.UUID;
@Repository
public class JooqCommunityParticipation implements CommunityParticipation {
    private final DSLContext db;
    public JooqCommunityParticipation(DSLContext db) {
        this.db = db;
    }
    public List<Entry> forPerson(UUID person, int limit, int offset) {
        return db
            .select(COMMUNITIES.ID, COMMUNITIES.KIND, COMMUNITIES.NAME, COMMUNITY_MEMBERSHIPS.ID,
                COMMUNITY_MEMBERSHIPS.STARTED_AT, COMMUNITY_MEMBERSHIPS.ENDED_AT)
            .from(COMMUNITY_MEMBERSHIPS)
            .join(COMMUNITIES)
            .on(COMMUNITIES.ID.eq(COMMUNITY_MEMBERSHIPS.COMMUNITY_ID))
            .where(COMMUNITY_MEMBERSHIPS.PERSON_ID.eq(person))
            .orderBy(COMMUNITY_MEMBERSHIPS.STARTED_AT.desc(), COMMUNITY_MEMBERSHIPS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Entry(r.value1(), r.value2(), r.value3(), r.value4(), r.value5().toInstant(),
                r.value6() == null ? null : r.value6().toInstant()));
    }
}

package org.onechurch.people.infrastructure.persistence;

import static org.onechurch.people.infrastructure.persistence.PeopleRecordMapper.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_RELATIONSHIPS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_RELATIONSHIP_GUARD;

import org.jooq.DSLContext;
import org.onechurch.people.application.contract.RelationshipReader;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.PersonRelationship;
import org.onechurch.people.domain.RelationshipType;
import org.onechurch.people.domain.repository.RelationshipRepository;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Repository
public class JooqRelationshipStore implements RelationshipRepository, RelationshipReader {

    private final DSLContext db;

    public JooqRelationshipStore(DSLContext db) {
        this.db = db;
    }

    @Override
    public void lockGraph() {
        db.select(PEOPLE_RELATIONSHIP_GUARD.ID)
            .from(PEOPLE_RELATIONSHIP_GUARD)
            .where(PEOPLE_RELATIONSHIP_GUARD.ID.eq((short) 1))
            .forUpdate()
            .fetchSingle();
    }

    @Override
    public Optional<PersonRelationship> find(UUID id) {
        return db.selectFrom(PEOPLE_RELATIONSHIPS)
            .where(PEOPLE_RELATIONSHIPS.ID.eq(id))
            .fetchOptional(PeopleRecordMapper::relationship);
    }

    @Override
    public List<PersonRelationship> forPerson(UUID id) {
        return db.selectFrom(PEOPLE_RELATIONSHIPS)
            .where(PEOPLE_RELATIONSHIPS.SOURCE_ID.eq(id).or(PEOPLE_RELATIONSHIPS.TARGET_ID.eq(id)))
            .orderBy(PEOPLE_RELATIONSHIPS.VALID_FROM, PEOPLE_RELATIONSHIPS.ID)
            .fetch(PeopleRecordMapper::relationship);
    }

    @Override
    public List<PersonRelationship> currentParentage(Instant at) {
        return db.selectFrom(PEOPLE_RELATIONSHIPS)
            .where(PEOPLE_RELATIONSHIPS.TYPE.eq(RelationshipType.PARENT_OF.name()))
            .and(PEOPLE_RELATIONSHIPS.VALID_FROM.le(time(at)))
            .and(PEOPLE_RELATIONSHIPS.VALID_TO.isNull().or(PEOPLE_RELATIONSHIPS.VALID_TO.gt(time(at))))
            .fetch(PeopleRecordMapper::relationship);
    }

    @Override
    public void insert(PersonRelationship relationship) {
        db.insertInto(PEOPLE_RELATIONSHIPS)
            .set(PEOPLE_RELATIONSHIPS.ID, relationship.id())
            .set(PEOPLE_RELATIONSHIPS.SOURCE_ID, relationship.sourceId())
            .set(PEOPLE_RELATIONSHIPS.TARGET_ID, relationship.targetId())
            .set(PEOPLE_RELATIONSHIPS.TYPE, relationship.type().name())
            .set(PEOPLE_RELATIONSHIPS.VALID_FROM, time(relationship.validFrom()))
            .set(PEOPLE_RELATIONSHIPS.VERSION, relationship.version())
            .execute();
    }

    @Override
    public void update(PersonRelationship relationship) {
        int changed = db.update(PEOPLE_RELATIONSHIPS)
            .set(PEOPLE_RELATIONSHIPS.VALID_TO, time(relationship.validTo()))
            .set(PEOPLE_RELATIONSHIPS.VERSION, relationship.version())
            .where(PEOPLE_RELATIONSHIPS.ID.eq(relationship.id()))
            .and(PEOPLE_RELATIONSHIPS.VERSION.eq(relationship.version() - 1))
            .execute();
        if (changed != 1) {
            throw PeopleException.conflict("Relationship has changed. Reload and try again.");
        }
    }
}

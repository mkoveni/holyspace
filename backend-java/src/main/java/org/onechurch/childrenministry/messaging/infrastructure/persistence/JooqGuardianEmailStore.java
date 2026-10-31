package org.onechurch.childrenministry.messaging.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_VERIFIED_EMAILS;

import org.jooq.DSLContext;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryWriter;
import org.onechurch.childrenministry.messaging.domain.VerifiedGuardianEmail;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenVerifiedEmailsRecord;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.Collection;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqGuardianEmailStore implements DeliveryReader, DeliveryWriter {
    private final DSLContext db;
    private final org.onechurch.communications.application.contract.DeliveryReader deliveries;
    public JooqGuardianEmailStore(DSLContext db,
        @Qualifier("communicationDeliveryStore") org.onechurch.communications.application.contract.DeliveryReader deliveries) {
        this.db = db;
        this.deliveries = deliveries;
    }
    private VerifiedGuardianEmail email(ChildrenVerifiedEmailsRecord r) {
        return new VerifiedGuardianEmail(r.getId(), r.getGuardianId(), r.getContactId(), r.getEmail(),
            r.getVerifiedBy(), ChildrenRecords.instant(r.getVerifiedAt()), ChildrenRecords.instant(r.getValidUntil()),
            r.getEvidence(), ChildrenRecords.instant(r.getRevokedAt()));
    }
    public List<VerifiedGuardianEmail> emails(UUID guardian) {
        return db.selectFrom(CHILDREN_VERIFIED_EMAILS)
            .where(CHILDREN_VERIFIED_EMAILS.GUARDIAN_ID.eq(guardian))
            .orderBy(CHILDREN_VERIFIED_EMAILS.VERIFIED_AT.desc())
            .limit(100)
            .fetch(this::email);
    }
    public Optional<VerifiedGuardianEmail> verification(UUID id) {
        return db.selectFrom(CHILDREN_VERIFIED_EMAILS)
            .where(CHILDREN_VERIFIED_EMAILS.ID.eq(id))
            .fetchOptional(this::email);
    }
    public void verify(VerifiedGuardianEmail e) {
        db.insertInto(CHILDREN_VERIFIED_EMAILS)
            .set(CHILDREN_VERIFIED_EMAILS.ID, e.id())
            .set(CHILDREN_VERIFIED_EMAILS.GUARDIAN_ID, e.guardianId())
            .set(CHILDREN_VERIFIED_EMAILS.CONTACT_ID, e.contactId())
            .set(CHILDREN_VERIFIED_EMAILS.EMAIL, e.email())
            .set(CHILDREN_VERIFIED_EMAILS.VERIFIED_BY, e.verifiedBy())
            .set(CHILDREN_VERIFIED_EMAILS.VERIFIED_AT, ChildrenRecords.time(e.verifiedAt()))
            .set(CHILDREN_VERIFIED_EMAILS.VALID_UNTIL, ChildrenRecords.time(e.validUntil()))
            .set(CHILDREN_VERIFIED_EMAILS.EVIDENCE, e.evidence())
            .execute();
    }
    public void revoke(UUID id, Instant at) {
        db.update(CHILDREN_VERIFIED_EMAILS)
            .set(CHILDREN_VERIFIED_EMAILS.REVOKED_AT, ChildrenRecords.time(at))
            .where(CHILDREN_VERIFIED_EMAILS.ID.eq(id))
            .and(CHILDREN_VERIFIED_EMAILS.REVOKED_AT.isNull())
            .execute();
    }

    public Map<UUID, Summary> summaries(Collection<UUID> ids) {
        return deliveries.summaries(ids);
    }
    public Optional<DeliverySnapshot> delivery(UUID id) {
        return deliveries.delivery(id).filter(value -> deliveries.belongsTo(value.id(), "children-ministry"));
    }
    public Optional<DeliverySnapshot> forCommunication(UUID id) {
        return deliveries.forCommunication(id).filter(value -> deliveries.belongsTo(value.id(), "children-ministry"));
    }
    public List<Event> events(UUID id, int limit, int offset) {
        return deliveries.belongsTo(id, "children-ministry") ? deliveries.events(id, limit, offset) : List.of();
    }
    public Page list(String status, int limit, int offset) {
        return deliveries.listForSource("children-ministry", status, limit, offset);
    }
    public Page listForSource(String context, String status, int limit, int offset) {
        return list(status, limit, offset);
    }
    public boolean belongsTo(UUID id, String context) {
        return context.equals("children-ministry") && deliveries.belongsTo(id, context);
    }
    public List<UUID> due(Instant at, int limit) {
        return deliveries.due(at, limit);
    }
    public Optional<Receipt> receipt(String key) {
        return deliveries.receipt(key);
    }
}

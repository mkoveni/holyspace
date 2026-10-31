package org.onechurch.communications.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_DELIVERY_EVENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_DELIVERY_SOURCES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_EMAIL_DELIVERIES;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.communications.application.DeliveryMapping;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.domain.EmailDelivery;
import org.onechurch.communications.domain.repository.DeliveryRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.CommunicationEmailDeliveriesRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.Collection;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.UUID;
@Repository("communicationDeliveryStore")
public class JooqDeliveryStore implements DeliveryReader, DeliveryRepository {
    private final DSLContext db;
    public JooqDeliveryStore(DSLContext db) {
        this.db = db;
    }
    private EmailDelivery map(CommunicationEmailDeliveriesRecord r) {
        return new EmailDelivery(r.getId(), r.getCommunicationId(), r.getVerificationId(), r.getContactId(),
            r.getRecipient(), r.getSubject(), r.getBody(), r.getReviewHash(), r.getApprovedBy(),
            CommunicationRecords.instant(r.getApprovedAt()), CommunicationRecords.instant(r.getScheduledAt()),
            r.getStatus(), r.getAttempts(), r.getMessageId(), r.getAttemptId(),
            CommunicationRecords.instant(r.getStartedAt()), CommunicationRecords.instant(r.getFinishedAt()),
            r.getErrorCode(), r.getCaptured(), r.getVersion(), r.getContentType(), r.getTemplateKey(),
            r.getTemplateVersion());
    }
    public Optional<DeliverySnapshot> delivery(UUID id) {
        return db.selectFrom(COMMUNICATION_EMAIL_DELIVERIES)
            .where(COMMUNICATION_EMAIL_DELIVERIES.ID.eq(id))
            .fetchOptional(row -> DeliveryMapping.snapshot(map(row)));
    }
    public Optional<EmailDelivery> lock(UUID id) {
        return db.selectFrom(COMMUNICATION_EMAIL_DELIVERIES)
            .where(COMMUNICATION_EMAIL_DELIVERIES.ID.eq(id))
            .forUpdate()
            .fetchOptional(this::map);
    }
    public Optional<DeliverySnapshot> forCommunication(UUID id) {
        return db.selectFrom(COMMUNICATION_EMAIL_DELIVERIES)
            .where(COMMUNICATION_EMAIL_DELIVERIES.COMMUNICATION_ID.eq(id))
            .fetchOptional(row -> DeliveryMapping.snapshot(map(row)));
    }
    public void insert(EmailDelivery d) {
        db.insertInto(COMMUNICATION_EMAIL_DELIVERIES)
            .set(COMMUNICATION_EMAIL_DELIVERIES.ID, d.id())
            .set(COMMUNICATION_EMAIL_DELIVERIES.COMMUNICATION_ID, d.communicationId())
            .set(COMMUNICATION_EMAIL_DELIVERIES.VERIFICATION_ID, d.verificationId())
            .set(COMMUNICATION_EMAIL_DELIVERIES.CONTACT_ID, d.contactId())
            .set(COMMUNICATION_EMAIL_DELIVERIES.RECIPIENT, d.recipient())
            .set(COMMUNICATION_EMAIL_DELIVERIES.SUBJECT, d.subject())
            .set(COMMUNICATION_EMAIL_DELIVERIES.BODY, d.body())
            .set(COMMUNICATION_EMAIL_DELIVERIES.CONTENT_TYPE, d.contentType())
            .set(COMMUNICATION_EMAIL_DELIVERIES.TEMPLATE_KEY, d.templateKey())
            .set(COMMUNICATION_EMAIL_DELIVERIES.TEMPLATE_VERSION, d.templateVersion())
            .set(COMMUNICATION_EMAIL_DELIVERIES.REVIEW_HASH, d.reviewHash())
            .set(COMMUNICATION_EMAIL_DELIVERIES.APPROVED_BY, d.approvedBy())
            .set(COMMUNICATION_EMAIL_DELIVERIES.APPROVED_AT, CommunicationRecords.time(d.approvedAt()))
            .set(COMMUNICATION_EMAIL_DELIVERIES.SCHEDULED_AT, CommunicationRecords.time(d.scheduledAt()))
            .set(COMMUNICATION_EMAIL_DELIVERIES.STATUS, d.status())
            .set(COMMUNICATION_EMAIL_DELIVERIES.MESSAGE_ID, d.messageId())
            .execute();
    }
    public void save(EmailDelivery d) {
        db.update(COMMUNICATION_EMAIL_DELIVERIES)
            .set(COMMUNICATION_EMAIL_DELIVERIES.STATUS, d.status())
            .set(COMMUNICATION_EMAIL_DELIVERIES.APPROVED_BY, d.approvedBy())
            .set(COMMUNICATION_EMAIL_DELIVERIES.APPROVED_AT, CommunicationRecords.time(d.approvedAt()))
            .set(COMMUNICATION_EMAIL_DELIVERIES.SCHEDULED_AT, CommunicationRecords.time(d.scheduledAt()))
            .set(COMMUNICATION_EMAIL_DELIVERIES.ATTEMPTS, d.attempts())
            .set(COMMUNICATION_EMAIL_DELIVERIES.ATTEMPT_ID, d.attemptId())
            .set(COMMUNICATION_EMAIL_DELIVERIES.STARTED_AT, CommunicationRecords.time(d.startedAt()))
            .set(COMMUNICATION_EMAIL_DELIVERIES.FINISHED_AT, CommunicationRecords.time(d.finishedAt()))
            .set(COMMUNICATION_EMAIL_DELIVERIES.ERROR_CODE, d.errorCode())
            .set(COMMUNICATION_EMAIL_DELIVERIES.CAPTURED, d.captured())
            .set(COMMUNICATION_EMAIL_DELIVERIES.VERSION, d.version())
            .where(COMMUNICATION_EMAIL_DELIVERIES.ID.eq(d.id()))
            .execute();
    }
    public void event(UUID id, String type, Instant at, UUID actor, String key, String hash, String reference) {
        db.insertInto(COMMUNICATION_DELIVERY_EVENTS)
            .set(COMMUNICATION_DELIVERY_EVENTS.ID, UUID.randomUUID())
            .set(COMMUNICATION_DELIVERY_EVENTS.DELIVERY_ID, id)
            .set(COMMUNICATION_DELIVERY_EVENTS.TYPE, type)
            .set(COMMUNICATION_DELIVERY_EVENTS.OCCURRED_AT, CommunicationRecords.time(at))
            .set(COMMUNICATION_DELIVERY_EVENTS.ACTOR_ID, actor)
            .set(COMMUNICATION_DELIVERY_EVENTS.EVENT_KEY, key)
            .set(COMMUNICATION_DELIVERY_EVENTS.EVENT_HASH, hash)
            .set(COMMUNICATION_DELIVERY_EVENTS.REFERENCE, reference)
            .execute();
    }
    public Optional<Receipt> receipt(String key) {
        return db.select(COMMUNICATION_DELIVERY_EVENTS.EVENT_HASH, COMMUNICATION_DELIVERY_EVENTS.DELIVERY_ID)
            .from(COMMUNICATION_DELIVERY_EVENTS)
            .where(COMMUNICATION_DELIVERY_EVENTS.EVENT_KEY.eq(key))
            .fetchOptional(r -> new Receipt(r.value1(), r.value2()));
    }
    public List<Event> events(UUID id, int limit, int offset) {
        return db
            .select(COMMUNICATION_DELIVERY_EVENTS.ID, COMMUNICATION_DELIVERY_EVENTS.TYPE,
                COMMUNICATION_DELIVERY_EVENTS.OCCURRED_AT, COMMUNICATION_DELIVERY_EVENTS.REFERENCE)
            .from(COMMUNICATION_DELIVERY_EVENTS)
            .where(COMMUNICATION_DELIVERY_EVENTS.DELIVERY_ID.eq(id))
            .orderBy(COMMUNICATION_DELIVERY_EVENTS.OCCURRED_AT.desc(), COMMUNICATION_DELIVERY_EVENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Event(r.value1(), r.value2(), CommunicationRecords.instant(r.value3()), r.value4()));
    }
    public Page list(String status, int limit, int offset) {
        return listWithScope(DSL.noCondition(), status, limit, offset);
    }
    public boolean belongsTo(UUID id, String sourceContext) {
        return db.fetchExists(COMMUNICATION_DELIVERY_SOURCES, COMMUNICATION_DELIVERY_SOURCES.DELIVERY_ID.eq(id)
            .and(COMMUNICATION_DELIVERY_SOURCES.SOURCE_CONTEXT.eq(sourceContext)));
    }
    public Page listForSource(String sourceContext, String status, int limit, int offset) {
        Condition scope = COMMUNICATION_EMAIL_DELIVERIES.ID.in(db.select(COMMUNICATION_DELIVERY_SOURCES.DELIVERY_ID)
            .from(COMMUNICATION_DELIVERY_SOURCES)
            .where(COMMUNICATION_DELIVERY_SOURCES.SOURCE_CONTEXT.eq(sourceContext)));
        return listWithScope(scope, status, limit, offset);
    }
    private Page listWithScope(Condition scope, String status, int limit, int offset) {
        Condition where = scope.and((status == null || status.isBlank() || status.equals("ALL")) ? DSL.noCondition()
            : COMMUNICATION_EMAIL_DELIVERIES.STATUS.eq(status));
        long failed = db.fetchCount(COMMUNICATION_EMAIL_DELIVERIES,
            scope.and(COMMUNICATION_EMAIL_DELIVERIES.STATUS.in("FAILED", "BLOCKED", "UNKNOWN")));
        long waiting = db.fetchCount(COMMUNICATION_EMAIL_DELIVERIES,
            scope.and(COMMUNICATION_EMAIL_DELIVERIES.STATUS.in("QUEUED", "SCHEDULED", "SENDING")));
        return new Page(
            db.select(COMMUNICATION_EMAIL_DELIVERIES.ID, COMMUNICATION_EMAIL_DELIVERIES.COMMUNICATION_ID,
                COMMUNICATION_EMAIL_DELIVERIES.STATUS, COMMUNICATION_EMAIL_DELIVERIES.SCHEDULED_AT,
                COMMUNICATION_EMAIL_DELIVERIES.ATTEMPTS, COMMUNICATION_EMAIL_DELIVERIES.ERROR_CODE,
                COMMUNICATION_EMAIL_DELIVERIES.CAPTURED, COMMUNICATION_EMAIL_DELIVERIES.VERSION)
                .from(COMMUNICATION_EMAIL_DELIVERIES)
                .where(where)
                .orderBy(COMMUNICATION_EMAIL_DELIVERIES.SCHEDULED_AT.desc(), COMMUNICATION_EMAIL_DELIVERIES.ID)
                .limit(limit)
                .offset(offset)
                .fetch(r -> new Summary(r.value1(), r.value2(), r.value3(), CommunicationRecords.instant(r.value4()),
                    r.value5(), r.value6(), r.value7(), r.value8())),
            db.fetchCount(COMMUNICATION_EMAIL_DELIVERIES, where), failed, waiting);
    }
    public List<UUID> due(Instant at, int limit) {
        return db.select(COMMUNICATION_EMAIL_DELIVERIES.ID)
            .from(COMMUNICATION_EMAIL_DELIVERIES)
            .where(COMMUNICATION_EMAIL_DELIVERIES.STATUS.in("QUEUED", "SCHEDULED"))
            .and(COMMUNICATION_EMAIL_DELIVERIES.SCHEDULED_AT.le(CommunicationRecords.time(at)))
            .orderBy(COMMUNICATION_EMAIL_DELIVERIES.SCHEDULED_AT, COMMUNICATION_EMAIL_DELIVERIES.ID)
            .limit(limit)
            .fetch(COMMUNICATION_EMAIL_DELIVERIES.ID);
    }
    public int recoverStale(Instant before, Instant now) {
        List<UUID> ids = db.select(COMMUNICATION_EMAIL_DELIVERIES.ID)
            .from(COMMUNICATION_EMAIL_DELIVERIES)
            .where(COMMUNICATION_EMAIL_DELIVERIES.STATUS.eq("SENDING"))
            .and(COMMUNICATION_EMAIL_DELIVERIES.STARTED_AT.lt(CommunicationRecords.time(before)))
            .limit(100)
            .forUpdate()
            .skipLocked()
            .fetch(COMMUNICATION_EMAIL_DELIVERIES.ID);
        for (UUID id : ids) {
            EmailDelivery d = lock(id).orElseThrow();
            save(d.transition("UNKNOWN", d.scheduledAt(), now, "PROCESS_INTERRUPTED", false));
            event(id, "UNKNOWN", now, null, null, null,
                "Sending was interrupted; reconcile with the provider before any new attempt.");
        }
        return ids.size();
    }
    public Map<UUID, Summary> summaries(Collection<UUID> ids) {
        return db
            .select(COMMUNICATION_EMAIL_DELIVERIES.ID, COMMUNICATION_EMAIL_DELIVERIES.COMMUNICATION_ID,
                COMMUNICATION_EMAIL_DELIVERIES.STATUS, COMMUNICATION_EMAIL_DELIVERIES.SCHEDULED_AT,
                COMMUNICATION_EMAIL_DELIVERIES.ATTEMPTS, COMMUNICATION_EMAIL_DELIVERIES.ERROR_CODE,
                COMMUNICATION_EMAIL_DELIVERIES.CAPTURED, COMMUNICATION_EMAIL_DELIVERIES.VERSION)
            .from(COMMUNICATION_EMAIL_DELIVERIES)
            .where(COMMUNICATION_EMAIL_DELIVERIES.COMMUNICATION_ID.in(ids))
            .fetchMap(COMMUNICATION_EMAIL_DELIVERIES.COMMUNICATION_ID, r -> new Summary(r.value1(), r.value2(),
                r.value3(), CommunicationRecords.instant(r.value4()), r.value5(), r.value6(), r.value7(), r.value8()));
    }

}

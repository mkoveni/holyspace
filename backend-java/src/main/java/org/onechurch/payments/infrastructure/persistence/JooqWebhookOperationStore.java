package org.onechurch.payments.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CONFIGURATION_REVISIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_ORDERS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_WEBHOOK_OPERATIONS;

import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.payments.domain.WebhookOperation;
import org.onechurch.payments.domain.repository.WebhookOperationRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsWebhookOperationsRecord;
import org.springframework.stereotype.Repository;

import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqWebhookOperationStore
    implements
        WebhookOperationRepository,
        org.onechurch.payments.application.contract.WebhookOperationReader {
    private final DSLContext db;
    public JooqWebhookOperationStore(DSLContext db) {
        this.db = db;
    }
    private WebhookOperation map(PaymentsWebhookOperationsRecord v) {
        return new WebhookOperation(v.getId(), v.getRevisionId(), v.getMode(), v.getKind(), v.getTargetId(),
            v.getBackendUrl(), v.getRequestHash(), v.getActorId(), v.getStatus(), v.getWebhookId(),
            v.getEncryptedSecret(), v.getCreatedAt().toInstant());
    }
    public Optional<WebhookOperation> find(UUID id) {
        return db.selectFrom(PAYMENTS_WEBHOOK_OPERATIONS)
            .where(PAYMENTS_WEBHOOK_OPERATIONS.ID.eq(id))
            .fetchOptional(this::map);
    }
    public void insert(WebhookOperation v) {
        db.insertInto(PAYMENTS_WEBHOOK_OPERATIONS)
            .set(PAYMENTS_WEBHOOK_OPERATIONS.ID, v.id())
            .set(PAYMENTS_WEBHOOK_OPERATIONS.REVISION_ID, v.revisionId())
            .set(PAYMENTS_WEBHOOK_OPERATIONS.MODE, v.mode())
            .set(PAYMENTS_WEBHOOK_OPERATIONS.KIND, v.kind())
            .set(PAYMENTS_WEBHOOK_OPERATIONS.TARGET_ID, v.targetId())
            .set(PAYMENTS_WEBHOOK_OPERATIONS.BACKEND_URL, v.backendUrl())
            .set(PAYMENTS_WEBHOOK_OPERATIONS.REQUEST_HASH, v.requestHash())
            .set(PAYMENTS_WEBHOOK_OPERATIONS.ACTOR_ID, v.actorId())
            .set(PAYMENTS_WEBHOOK_OPERATIONS.STATUS, v.status())
            .set(PAYMENTS_WEBHOOK_OPERATIONS.CREATED_AT, v.createdAt().atOffset(ZoneOffset.UTC))
            .execute();
    }
    public void finish(UUID id, String status, String webhook, String secret) {
        db.update(PAYMENTS_WEBHOOK_OPERATIONS)
            .set(PAYMENTS_WEBHOOK_OPERATIONS.STATUS, status)
            .set(PAYMENTS_WEBHOOK_OPERATIONS.WEBHOOK_ID, webhook)
            .set(PAYMENTS_WEBHOOK_OPERATIONS.ENCRYPTED_SECRET, secret)
            .where(PAYMENTS_WEBHOOK_OPERATIONS.ID.eq(id))
            .execute();
    }
    public List<WebhookOperation> pending(String mode, UUID actor) {
        return db.selectFrom(PAYMENTS_WEBHOOK_OPERATIONS)
            .where(PAYMENTS_WEBHOOK_OPERATIONS.MODE.eq(mode)

                .and(PAYMENTS_WEBHOOK_OPERATIONS.STATUS.in("STARTED", "UNKNOWN", "READY")))
            .orderBy(PAYMENTS_WEBHOOK_OPERATIONS.CREATED_AT.desc())
            .limit(20)
            .fetch(this::map);
    }
    public boolean referenced(String mode, String backend) {
        if (db.fetchExists(db.selectOne()
            .from(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.PROVIDER.eq("yoco")
                .and(PAYMENTS_ORDERS.MODE.eq(mode))
                .and(PAYMENTS_ORDERS.CONFIGURATION_ID.isNull())))) {
            return true;
        }
        return db.fetchExists(db.selectOne()
            .from(PAYMENTS_ORDERS)
            .join(PAYMENTS_CONFIGURATION_REVISIONS)
            .on(PAYMENTS_ORDERS.CONFIGURATION_ID.eq(PAYMENTS_CONFIGURATION_REVISIONS.ID))
            .where(PAYMENTS_ORDERS.PROVIDER.eq("yoco")
                .and(PAYMENTS_ORDERS.MODE.eq(mode))
                .and(DSL
                    .coalesce(PAYMENTS_CONFIGURATION_REVISIONS.BACKEND_URL, PAYMENTS_CONFIGURATION_REVISIONS.PUBLIC_URL)
                    .eq(backend))));
    }
}

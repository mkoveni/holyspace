package org.onechurch.payments.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_MOBILE_CHECKOUTS;

import org.jooq.DSLContext;
import org.onechurch.payments.domain.MobileCheckout;
import org.onechurch.payments.domain.repository.MobileCheckoutRepository;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.ZoneOffset;
import java.util.Optional;
@Repository
public class JooqMobileCheckoutStore implements MobileCheckoutRepository {
    private final DSLContext db;
    public JooqMobileCheckoutStore(DSLContext db) {
        this.db = db;
    }
    public void insert(MobileCheckout c) {
        db.insertInto(PAYMENTS_MOBILE_CHECKOUTS)
            .set(PAYMENTS_MOBILE_CHECKOUTS.TOKEN_HASH, c.tokenHash())
            .set(PAYMENTS_MOBILE_CHECKOUTS.ORDER_ID, c.orderId())
            .set(PAYMENTS_MOBILE_CHECKOUTS.ACCOUNT_ID, c.accountId())
            .set(PAYMENTS_MOBILE_CHECKOUTS.SESSION_ID, c.sessionId())
            .set(PAYMENTS_MOBILE_CHECKOUTS.ENCRYPTED_CHECKOUT, c.encryptedCheckout())
            .set(PAYMENTS_MOBILE_CHECKOUTS.EXPIRES_AT, c.expiresAt().atOffset(ZoneOffset.UTC))
            .execute();
    }
    public Optional<MobileCheckout> lock(String hash) {
        return db.selectFrom(PAYMENTS_MOBILE_CHECKOUTS)
            .where(PAYMENTS_MOBILE_CHECKOUTS.TOKEN_HASH.eq(hash))
            .forUpdate()
            .fetchOptional(r -> new MobileCheckout(r.getTokenHash(), r.getOrderId(), r.getAccountId(), r.getSessionId(),
                r.getEncryptedCheckout(), r.getExpiresAt().toInstant(),
                r.getUsedAt() == null ? null : r.getUsedAt().toInstant()));
    }
    public void used(String hash, Instant now) {
        db.update(PAYMENTS_MOBILE_CHECKOUTS)
            .set(PAYMENTS_MOBILE_CHECKOUTS.USED_AT, now.atOffset(ZoneOffset.UTC))
            .where(PAYMENTS_MOBILE_CHECKOUTS.TOKEN_HASH.eq(hash).and(PAYMENTS_MOBILE_CHECKOUTS.USED_AT.isNull()))
            .execute();
    }
}

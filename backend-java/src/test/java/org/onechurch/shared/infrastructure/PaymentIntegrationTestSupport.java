package org.onechurch.shared.infrastructure;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_ORDERS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.BeforeEach;

import java.util.List;
import java.util.UUID;
public abstract class PaymentIntegrationTestSupport extends GivingIntegrationTestSupport {
    @BeforeEach
    protected void resetPayments() {
        db.truncate(PAYMENTS_ORDERS).cascade().execute();
        db.truncate(org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CONFIGURATION_REVISIONS)
            .cascade()
            .execute();
        db.truncate(org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CONFIGURATION_HISTORY).execute();
        db.update(org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS)
            .set(org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS.MANAGED, false)
            .set(org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS.ENABLED, false)
            .set(org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS.MONTHLY, false)
            .set(org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS.MODE, "test")
            .set(org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_PROVIDER_SETTINGS.VERSION, 0L)
            .execute();
        for (String code : List.of("payments.manage", "payments.refund", "payments.reconcile", "payments.configure",
            "payments.receipts.send")) {
            UUID id = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, id).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, id)
                .execute();
        }
    }
}

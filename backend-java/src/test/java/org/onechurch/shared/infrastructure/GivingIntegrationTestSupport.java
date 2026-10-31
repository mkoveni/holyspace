package org.onechurch.shared.infrastructure;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_FUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_RECONCILIATION_BATCHES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.BeforeEach;

import java.time.LocalDate;
import java.util.List;
import java.util.Map;
import java.util.UUID;
public abstract class GivingIntegrationTestSupport extends PeopleIntegrationTestSupport {
    protected static final String GIVING = "/api/giving";
    @BeforeEach
    protected void resetGiving() {
        db.truncate(GIVING_RECONCILIATION_BATCHES).cascade().execute();
        db.truncate(GIVING_GIFTS).cascade().execute();
        db.truncate(GIVING_FUNDS).cascade().execute();
        db.truncate(GIVING_HISTORY).execute();
        for (String code : List.of("giving.read", "giving.manage", "giving.reverse", "giving.acknowledge",
            "giving.reconcile", "giving.export")) {
            UUID id = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, id).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, id)
                .execute();
        }
    }
    protected UUID fund(String name) throws Exception {
        return created(request("POST", GIVING + "/funds", admin,
            Map.of("requestId", UUID.randomUUID(), "name", name, "description", "Church purpose")));
    }
    protected Map<String, Object> giftBody(UUID fund, LocalDate date, long amount) {
        return Map.of("requestId", UUID.randomUUID(), "amountMinor", amount, "currency", "ZAR", "receivedOn",
            date.toString(), "method", "BANK", "allocations", List.of(Map.of("fundId", fund, "amountMinor", amount)));
    }
    protected UUID gift(UUID fund, LocalDate date, long amount) throws Exception {
        return created(request("POST", GIVING + "/gifts", admin, giftBody(fund, date, amount)));
    }
    protected void revoke(String code) {
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.ROLE_ID.eq(ADMIN_ROLE)
                .and(ROLE_PERMISSIONS.PERMISSION_ID
                    .in(db.select(PERMISSIONS.ID).from(PERMISSIONS).where(PERMISSIONS.CODE.eq(code)))))
            .execute();
    }
    protected String period(LocalDate from, LocalDate to) {
        return "?from=" + from + "&to=" + to;
    }
}

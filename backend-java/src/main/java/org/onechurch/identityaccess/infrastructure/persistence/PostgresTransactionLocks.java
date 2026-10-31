package org.onechurch.identityaccess.infrastructure.persistence;

import static org.jooq.impl.DSL.function;
import static org.jooq.impl.DSL.name;
import static org.jooq.impl.DSL.val;

import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.impl.SQLDataType;

final class PostgresTransactionLocks {

    private PostgresTransactionLocks() {
    }

    static void lockAccessChanges(DSLContext db) {
        lock(db, val(78219401L));
    }

    static void lockLogin(DSLContext db, String identifierHash) {
        Field<Long> key = function(name("hashtextextended"), SQLDataType.BIGINT, val(identifierHash), val(0L));
        lock(db, key);
    }

    private static void lock(DSLContext db, Field<Long> key) {
        db.select(function(name("pg_advisory_xact_lock"), SQLDataType.OTHER, key)).fetch();
    }
}

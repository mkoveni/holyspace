package org.onechurch.identityaccess.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ACCOUNTS;

import org.jooq.DSLContext;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.repository.AccountRepository;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Repository
public class JooqAccountStore implements AccountReader, AccountRepository {

    private final DSLContext db;

    public JooqAccountStore(DSLContext db) {
        this.db = db;
    }

    @Override
    public Optional<Account> find(UUID id) {
        return db.selectFrom(USER_ACCOUNTS).where(USER_ACCOUNTS.ID.eq(id)).fetchOptional(AccountRecordMapper::toDomain);
    }

    @Override
    public Optional<Account> findByLogin(String identifier) {
        return db.selectFrom(USER_ACCOUNTS)
            .where(USER_ACCOUNTS.USERNAME.eq(identifier).or(USER_ACCOUNTS.EMAIL.eq(identifier)))
            .fetchOptional(AccountRecordMapper::toDomain);
    }

    @Override
    public List<Account> search(String search, int limit) {
        return db.selectFrom(USER_ACCOUNTS)
            .where(USER_ACCOUNTS.USERNAME.contains(search)
                .or(USER_ACCOUNTS.EMAIL.contains(search))
                .or(USER_ACCOUNTS.DISPLAY_NAME.containsIgnoreCase(search)))
            .orderBy(USER_ACCOUNTS.DISPLAY_NAME, USER_ACCOUNTS.ID)
            .limit(limit)
            .fetch(AccountRecordMapper::toDomain);
    }

    @Override
    public boolean any() {
        return db.fetchExists(db.selectOne().from(USER_ACCOUNTS));
    }

    @Override
    public Optional<Account> findForUpdate(UUID id) {
        return db.selectFrom(USER_ACCOUNTS)
            .where(USER_ACCOUNTS.ID.eq(id))
            .forUpdate()
            .fetchOptional(AccountRecordMapper::toDomain);
    }

    @Override
    public void save(Account a) {
        db.insertInto(USER_ACCOUNTS)
            .set(USER_ACCOUNTS.ID, a.id())
            .set(USER_ACCOUNTS.USERNAME, a.username())
            .set(USER_ACCOUNTS.EMAIL, a.email())
            .set(USER_ACCOUNTS.DISPLAY_NAME, a.displayName())
            .set(USER_ACCOUNTS.PASSWORD_HASH, a.passwordHash())
            .set(USER_ACCOUNTS.STATUS, a.status())
            .set(USER_ACCOUNTS.CREATED_AT, time(a.createdAt()))
            .set(USER_ACCOUNTS.UPDATED_AT, time(a.updatedAt()))
            .onConflict(USER_ACCOUNTS.ID)
            .doUpdate()
            .set(USER_ACCOUNTS.EMAIL, a.email())
            .set(USER_ACCOUNTS.DISPLAY_NAME, a.displayName())
            .set(USER_ACCOUNTS.PASSWORD_HASH, a.passwordHash())
            .set(USER_ACCOUNTS.STATUS, a.status())
            .set(USER_ACCOUNTS.UPDATED_AT, time(a.updatedAt()))
            .execute();
    }

    private static OffsetDateTime time(Instant instant) {
        return instant.atOffset(ZoneOffset.UTC);
    }
}

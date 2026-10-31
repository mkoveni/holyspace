package org.onechurch.identityaccess.infrastructure.persistence;

import org.onechurch.identityaccess.domain.Account;
import org.onechurch.shared.infrastructure.jooq.tables.records.UserAccountsRecord;

final class AccountRecordMapper {
    private AccountRecordMapper() {
    }

    static Account toDomain(UserAccountsRecord r) {
        return Account.reconstitute(r.getId(), r.getUsername(), r.getEmail(), r.getDisplayName(), r.getPasswordHash(),
            r.getStatus(), r.getCreatedAt().toInstant(), r.getUpdatedAt().toInstant());
    }
}

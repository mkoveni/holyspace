package org.onechurch.identityaccess.domain.repository;

import org.onechurch.identityaccess.domain.Account;

import java.util.Optional;
import java.util.UUID;

/** Loads and persists complete account aggregates for command use cases. */
public interface AccountRepository {

    Optional<Account> find(UUID id);

    Optional<Account> findForUpdate(UUID id);

    void save(Account account);
}

package org.onechurch.identityaccess.application.contract;

import org.onechurch.identityaccess.domain.Account;

import java.util.List;
import java.util.Optional;
import java.util.UUID;

public interface AccountReader {

    Optional<Account> find(UUID id);

    Optional<Account> findByLogin(String identifier);

    List<Account> search(String search, int limit);

    boolean any();
}

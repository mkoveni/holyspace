package org.onechurch.identityaccess.infrastructure;
import org.onechurch.identityaccess.application.contract.AccountIdentityGuard;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.repository.AccountRepository;
import org.springframework.stereotype.Component;

import java.util.UUID;
@Component
public final class PublishedAccountIdentityGuard implements AccountIdentityGuard {
    private final AccountRepository accounts;
    public PublishedAccountIdentityGuard(AccountRepository accounts) {
        this.accounts = accounts;
    }
    public void lock(UUID account) {
        accounts.findForUpdate(account)
            .orElseThrow(() -> new IdentityException(IdentityException.Kind.NOT_FOUND, "Account was not found."));
    }
}

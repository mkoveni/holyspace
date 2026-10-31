package org.onechurch.identityaccess.infrastructure;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.domain.Account;
import org.springframework.stereotype.Component;

import java.util.UUID;
@Component
public class PublishedAccountDirectory implements AccountDirectory {
    private final AccountReader accounts;
    public PublishedAccountDirectory(AccountReader accounts) {
        this.accounts = accounts;
    }
    public boolean active(UUID accountId) {
        return accounts.find(accountId).filter(Account::active).isPresent();
    }
}

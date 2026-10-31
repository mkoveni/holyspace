package org.onechurch.identityaccess.infrastructure;
import org.onechurch.identityaccess.application.contract.AccessReader;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.IdentityRules;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.UUID;
@Component
public final class PublishedResponsibleAccountDirectory implements ResponsibleAccountDirectory {
    private final AccountReader accounts;
    private final AccessReader access;
    public PublishedResponsibleAccountDirectory(AccountReader accounts, AccessReader access) {
        this.accounts = accounts;
        this.access = access;
    }
    public boolean eligible(UUID id, String permission) {
        return accounts.find(id)
            .filter(Account::active)
            .filter(account -> IdentityRules.effectivePermissions(access.permissionCodes(id)).contains(permission))
            .isPresent();
    }
    public List<Entry> search(String permission, String search) {
        return accounts.search(search, 100)
            .stream()
            .filter(Account::active)
            .filter(account -> eligible(account.id(), permission))
            .limit(25)
            .map(account -> new Entry(account.id(), account.displayName()))
            .toList();
    }
}

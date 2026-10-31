package org.onechurch.identityaccess.application.query.listaccounts;

import org.onechurch.identityaccess.application.AccountView;
import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.domain.IdentityRules;

import java.util.List;
import java.util.Set;

public final class ListAccountsHandler {

    private final Authorization authorization;

    private final AccountReader accounts;

    public ListAccountsHandler(AccountReader accounts, Authorization authorization) {
        this.authorization = authorization;
        this.accounts = accounts;
    }

    public List<AccountView> handle(ListAccountsQuery query) {
        authorization.requirePermission("identity.manage");
        return accounts.search(IdentityRules.normalize(query.search()), Math.clamp(query.limit(), 1, 100))
            .stream()
            .map(account -> AccountView.from(account, Set.of(), Set.of()))
            .toList();
    }
}

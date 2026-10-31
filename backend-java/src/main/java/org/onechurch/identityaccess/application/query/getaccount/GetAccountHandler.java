package org.onechurch.identityaccess.application.query.getaccount;

import org.onechurch.identityaccess.application.AccountView;
import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccessReader;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.IdentityRules;

public final class GetAccountHandler {

    private final Authorization authorization;

    private final AccountReader accounts;
    private final AccessReader access;

    public GetAccountHandler(AccountReader accounts, AccessReader access, Authorization authorization) {
        this.authorization = authorization;
        this.accounts = accounts;
        this.access = access;
    }

    public AccountView handle(GetAccountQuery query) {
        authorization.requireSelfOrPermission(query.id(), "identity.manage");
        return accounts.find(query.id())
            .map(this::mapToView)
            .orElseThrow(() -> new IdentityException(IdentityException.Kind.NOT_FOUND, "Account not found"));
    }

    private AccountView mapToView(Account account) {
        return AccountView.from(account, access.roleNames(account.id()),
            IdentityRules.effectivePermissions(access.permissionCodes(account.id())));
    }
}

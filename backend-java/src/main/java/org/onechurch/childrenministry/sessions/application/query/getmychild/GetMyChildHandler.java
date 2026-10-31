package org.onechurch.childrenministry.sessions.application.query.getmychild;
import org.onechurch.childrenministry.sessions.application.MemberFamilyViews;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
public final class GetMyChildHandler {
    private final MemberIdentity identity;
    private final AuthenticatedAccount account;
    private final MemberFamilyViews views;
    public GetMyChildHandler(MemberIdentity identity, AuthenticatedAccount account, MemberFamilyViews views) {
        this.identity = identity;
        this.account = account;
        this.views = views;
    }
    public MemberFamilyViews.FamilyChild handle(GetMyChildQuery q) {
        return views.require(identity.requirePerson(account.requireAccount()), q.childId());
    }
}

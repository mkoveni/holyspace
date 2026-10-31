package org.onechurch.childrenministry.sessions.application.query.listmychildsessions;
import org.onechurch.childrenministry.sessions.application.MemberFamilyViews;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
public final class ListMyChildSessionsHandler {
    private final MemberIdentity identity;
    private final AuthenticatedAccount account;
    private final MemberFamilyViews views;
    public ListMyChildSessionsHandler(MemberIdentity identity, AuthenticatedAccount account, MemberFamilyViews views) {
        this.identity = identity;
        this.account = account;
        this.views = views;
    }
    public MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilySession> handle(ListMyChildSessionsQuery q) {
        return views.sessions(identity.requirePerson(account.requireAccount()), q.childId(), q.limit(), q.offset());
    }
}

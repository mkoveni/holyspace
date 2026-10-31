package org.onechurch.childrenministry.sessions.application.query.listmychildprogrammes;
import org.onechurch.childrenministry.sessions.application.MemberFamilyViews;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
public final class ListMyChildProgrammesHandler {
    private final MemberIdentity identity;
    private final AuthenticatedAccount account;
    private final MemberFamilyViews views;
    public ListMyChildProgrammesHandler(MemberIdentity identity, AuthenticatedAccount account,
        MemberFamilyViews views) {
        this.identity = identity;
        this.account = account;
        this.views = views;
    }
    public MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilyProgramme> handle(ListMyChildProgrammesQuery q) {
        return views.programmes(identity.requirePerson(account.requireAccount()), q.childId(), q.limit(), q.offset());
    }
}

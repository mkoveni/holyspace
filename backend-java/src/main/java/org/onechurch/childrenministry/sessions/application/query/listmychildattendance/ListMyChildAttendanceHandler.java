package org.onechurch.childrenministry.sessions.application.query.listmychildattendance;
import org.onechurch.childrenministry.sessions.application.MemberFamilyViews;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
public final class ListMyChildAttendanceHandler {
    private final MemberIdentity identity;
    private final AuthenticatedAccount account;
    private final MemberFamilyViews views;
    public ListMyChildAttendanceHandler(MemberIdentity identity, AuthenticatedAccount account,
        MemberFamilyViews views) {
        this.identity = identity;
        this.account = account;
        this.views = views;
    }
    public MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilyVisit> handle(ListMyChildAttendanceQuery q) {
        return views.attendance(identity.requirePerson(account.requireAccount()), q.childId(), q.limit(), q.offset());
    }
}

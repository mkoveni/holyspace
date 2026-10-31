package org.onechurch.childrenministry.sessions.application.query.listmychildren;
import org.onechurch.childrenministry.sessions.application.MemberFamilyViews;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.MemberIdentity;

import java.util.List;
public final class ListMyChildrenHandler {
    private final MemberIdentity identity;
    private final AuthenticatedAccount account;
    private final MemberFamilyViews views;
    public ListMyChildrenHandler(MemberIdentity identity, AuthenticatedAccount account, MemberFamilyViews views) {
        this.identity = identity;
        this.account = account;
        this.views = views;
    }
    public MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilyChild> handle(ListMyChildrenQuery q) {
        return identity.currentPerson().map(p -> views.children(p, q.limit(), q.offset())).orElseGet(() -> {
            ChildrenRules.page(q.limit(), q.offset());
            return new MemberFamilyViews.FamilyPage<>(false, List.of(), false);
        });
    }
}

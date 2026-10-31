package org.onechurch.volunteering.application.query.getmyvolunteering;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.MemberVolunteerViews;

import java.util.UUID;
public final class GetMyVolunteeringHandler {
    private final AuthenticatedAccount identity;
    private final PermissionChecker auth;
    private final MemberVolunteerViews views;
    public GetMyVolunteeringHandler(AuthenticatedAccount identity, PermissionChecker auth, MemberVolunteerViews views) {
        this.identity = identity;
        this.auth = auth;
        this.views = views;
    }
    public MemberVolunteerViews.Overview handle(GetMyVolunteeringQuery query) {
        UUID account = identity.requireAccount();
        return views.overview(account, auth.hasPermission("volunteering.serve"), query.limit(), query.offset());
    }
}

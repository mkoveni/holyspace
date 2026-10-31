package org.onechurch.communities.application;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.communities.domain.Community;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.time.Clock;
import java.util.UUID;
public final class CommunityAccess {
    private final PermissionChecker auth;
    private final CurrentActor actors;
    private final CommunityReader reader;
    private final Clock clock;
    public CommunityAccess(PermissionChecker auth, CurrentActor actors, CommunityReader reader, Clock clock) {
        this.auth = auth;
        this.actors = actors;
        this.reader = reader;
        this.clock = clock;
    }
    public void manager(UUID actor) {
        auth.requirePermission(actor, "ministries.manage");
    }
    public UUID caller() {
        return actors.current()
            .orElseThrow(() -> new CommunityException(CommunityException.Kind.FORBIDDEN, "Sign in to continue."))
            .accountId();
    }
    public UUID directoryScope() {
        if (auth.hasPermission("ministries.read") || auth.hasPermission("ministries.manage")) {
            auth.requirePermission("ministries.read");
            return null;
        }
        auth.requirePermission("ministries.lead");
        return caller();
    }
    public boolean canOperate(UUID community) {
        return auth.hasPermission("ministries.manage")
            || (auth.hasPermission("ministries.lead") && reader.leads(community, caller(), clock.instant()));
    }
    public void require(UUID community) {
        if (auth.hasPermission("ministries.manage")) {
            auth.requirePermission("ministries.manage");
            return;
        }
        auth.requirePermission("ministries.lead");
        if (!reader.leads(community, caller(), clock.instant())) {
            throw new CommunityException(CommunityException.Kind.FORBIDDEN,
                "This community is outside your current leadership scope.");
        }
    }
    public void actor(UUID actor) {
        auth.requirePermission(actor,
            auth.hasPermission("ministries.manage") ? "ministries.manage" : "ministries.lead");
    }
    public Community community(UUID id) {
        return reader.community(id).orElseThrow(CommunityException::missing);
    }
}

package org.onechurch.communities.presentation;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.communities.application.command.appointcommunityleader.AppointCommunityLeaderCommand;
import org.onechurch.communities.application.command.appointcommunityleader.AppointCommunityLeaderHandler;
import org.onechurch.communities.application.command.createcommunity.CreateCommunityCommand;
import org.onechurch.communities.application.command.createcommunity.CreateCommunityHandler;
import org.onechurch.communities.application.command.endcommunityleadership.EndCommunityLeadershipCommand;
import org.onechurch.communities.application.command.endcommunityleadership.EndCommunityLeadershipHandler;
import org.onechurch.communities.application.command.joincommunity.JoinCommunityCommand;
import org.onechurch.communities.application.command.joincommunity.JoinCommunityHandler;
import org.onechurch.communities.application.command.leavecommunity.LeaveCommunityCommand;
import org.onechurch.communities.application.command.leavecommunity.LeaveCommunityHandler;
import org.onechurch.communities.application.command.updatecommunity.UpdateCommunityCommand;
import org.onechurch.communities.application.command.updatecommunity.UpdateCommunityHandler;
import org.onechurch.communities.application.contract.CommunityParticipation;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.communities.application.query.getcommunity.GetCommunityHandler;
import org.onechurch.communities.application.query.getcommunity.GetCommunityQuery;
import org.onechurch.communities.application.query.getcommunityhistory.GetCommunityHistoryHandler;
import org.onechurch.communities.application.query.getcommunityhistory.GetCommunityHistoryQuery;
import org.onechurch.communities.application.query.listcommunities.ListCommunitiesHandler;
import org.onechurch.communities.application.query.listcommunities.ListCommunitiesQuery;
import org.onechurch.communities.application.query.listcommunityleadership.ListCommunityLeadershipHandler;
import org.onechurch.communities.application.query.listcommunityleadership.ListCommunityLeadershipQuery;
import org.onechurch.communities.application.query.listcommunitymembers.ListCommunityMembersHandler;
import org.onechurch.communities.application.query.listcommunitymembers.ListCommunityMembersQuery;
import org.onechurch.communities.application.query.listpersoncommunities.ListPersonCommunitiesHandler;
import org.onechurch.communities.application.query.listpersoncommunities.ListPersonCommunitiesQuery;
import org.onechurch.communities.presentation.api.CommunityDirectoryApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
@PreAuthorize("hasAnyAuthority('ministries.read','ministries.manage','ministries.lead')")
public class CommunityDirectoryController implements CommunityDirectoryApi {
    private final ListPersonCommunitiesHandler personCommunities;

    private final CreateCommunityHandler createCommunityHandler;
    private final UpdateCommunityHandler updateCommunityHandler;
    private final JoinCommunityHandler joinCommunityHandler;
    private final LeaveCommunityHandler leaveCommunityHandler;
    private final AppointCommunityLeaderHandler appointCommunityLeaderHandler;
    private final EndCommunityLeadershipHandler endCommunityLeadershipHandler;
    private final ListCommunitiesHandler listCommunitiesHandler;
    private final GetCommunityHandler getCommunityHandler;
    private final ListCommunityMembersHandler listCommunityMembersHandler;
    private final ListCommunityLeadershipHandler listCommunityLeadershipHandler;
    private final GetCommunityHistoryHandler getCommunityHistoryHandler;
    public CommunityDirectoryController(ListPersonCommunitiesHandler personCommunities,
        CreateCommunityHandler createCommunityHandler, UpdateCommunityHandler updateCommunityHandler,
        JoinCommunityHandler joinCommunityHandler, LeaveCommunityHandler leaveCommunityHandler,
        AppointCommunityLeaderHandler appointCommunityLeaderHandler,
        EndCommunityLeadershipHandler endCommunityLeadershipHandler, ListCommunitiesHandler listCommunitiesHandler,
        GetCommunityHandler getCommunityHandler, ListCommunityMembersHandler listCommunityMembersHandler,
        ListCommunityLeadershipHandler listCommunityLeadershipHandler,
        GetCommunityHistoryHandler getCommunityHistoryHandler) {
        this.personCommunities = personCommunities;
        this.createCommunityHandler = createCommunityHandler;
        this.updateCommunityHandler = updateCommunityHandler;
        this.joinCommunityHandler = joinCommunityHandler;
        this.leaveCommunityHandler = leaveCommunityHandler;
        this.appointCommunityLeaderHandler = appointCommunityLeaderHandler;
        this.endCommunityLeadershipHandler = endCommunityLeadershipHandler;
        this.listCommunitiesHandler = listCommunitiesHandler;
        this.getCommunityHandler = getCommunityHandler;
        this.listCommunityMembersHandler = listCommunityMembersHandler;
        this.listCommunityLeadershipHandler = listCommunityLeadershipHandler;
        this.getCommunityHistoryHandler = getCommunityHistoryHandler;
    }
    @Override
    public Created createCommunity(CreateCommunityBody body, OAuth2AuthenticatedPrincipal actor) {
        return new Created(createCommunityHandler.handle(new CreateCommunityCommand(UUID.fromString(actor.getName()),
            body.requestId(), body.kind(), body.name(), body.description(), body.parentId(), body.campus())));
    }
    @Override
    public void updateCommunity(UUID communityId, UpdateCommunityBody body, OAuth2AuthenticatedPrincipal actor) {
        updateCommunityHandler
            .handle(new UpdateCommunityCommand(UUID.fromString(actor.getName()), communityId, body.expectedVersion(),
                body.name(), body.description(), body.parentId(), body.campus(), body.active(), body.reason()));
    }
    @Override
    public Created joinCommunity(UUID communityId, JoinCommunityBody body, OAuth2AuthenticatedPrincipal actor) {
        return new Created(joinCommunityHandler.handle(new JoinCommunityCommand(UUID.fromString(actor.getName()),
            body.requestId(), communityId, body.personId())));
    }
    @Override
    public void leaveCommunity(UUID membershipId, LeaveCommunityBody body, OAuth2AuthenticatedPrincipal actor) {
        leaveCommunityHandler.handle(new LeaveCommunityCommand(UUID.fromString(actor.getName()), membershipId,
            body.expectedVersion(), body.reason()));
    }
    @Override
    public Created appointCommunityLeader(UUID membershipId, AppointCommunityLeaderBody body,
        OAuth2AuthenticatedPrincipal actor) {
        return new Created(
            appointCommunityLeaderHandler.handle(new AppointCommunityLeaderCommand(UUID.fromString(actor.getName()),
                body.requestId(), membershipId, body.expectedVersion(), body.role(), body.accountId(), body.reason())));
    }
    @Override
    public void endCommunityLeadership(UUID leadershipId, EndCommunityLeadershipBody body,
        OAuth2AuthenticatedPrincipal actor) {
        endCommunityLeadershipHandler.handle(new EndCommunityLeadershipCommand(UUID.fromString(actor.getName()),
            leadershipId, body.expectedVersion(), body.reason()));
    }
    @Override
    public CommunityViews.Directory listCommunities(String kind, String search, boolean archived, int limit,
        int offset) {
        return listCommunitiesHandler.handle(new ListCommunitiesQuery(kind, search, archived, limit, offset));
    }
    @Override
    public CommunityViews.Details getCommunity(UUID communityId) {
        return getCommunityHandler.handle(new GetCommunityQuery(communityId));
    }
    @Override
    public CommunityViews.Members listCommunityMembers(UUID communityId, boolean ended, int limit, int offset) {
        return listCommunityMembersHandler.handle(new ListCommunityMembersQuery(communityId, ended, limit, offset));
    }
    @Override
    public List<CommunityViews.Appointment> listCommunityLeadership(UUID communityId, int limit, int offset) {
        return listCommunityLeadershipHandler.handle(new ListCommunityLeadershipQuery(communityId, limit, offset));
    }
    @Override
    public List<CommunityReader.History> getCommunityHistory(UUID communityId, int limit, int offset) {
        return getCommunityHistoryHandler.handle(new GetCommunityHistoryQuery(communityId, limit, offset));
    }
    @Override
    public List<CommunityParticipation.Entry> personCommunities(UUID personId, int limit, int offset) {
        return personCommunities.handle(new ListPersonCommunitiesQuery(personId, limit, offset));
    }
}

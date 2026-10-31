package org.onechurch.communities.presentation;
import org.onechurch.communities.application.command.completecommunityfollowup.CompleteCommunityFollowUpCommand;
import org.onechurch.communities.application.command.completecommunityfollowup.CompleteCommunityFollowUpHandler;
import org.onechurch.communities.application.command.createcommunityfollowup.CreateCommunityFollowUpCommand;
import org.onechurch.communities.application.command.createcommunityfollowup.CreateCommunityFollowUpHandler;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.communities.application.query.listcommunityfollowups.ListCommunityFollowUpsHandler;
import org.onechurch.communities.application.query.listcommunityfollowups.ListCommunityFollowUpsQuery;
import org.onechurch.communities.presentation.api.CommunityFollowUpsApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
@PreAuthorize("hasAnyAuthority('ministries.manage','ministries.lead')")
public class CommunityFollowUpsController implements CommunityFollowUpsApi {
    private final CreateCommunityFollowUpHandler createCommunityFollowUpHandler;
    private final CompleteCommunityFollowUpHandler completeCommunityFollowUpHandler;
    private final ListCommunityFollowUpsHandler listCommunityFollowUpsHandler;
    public CommunityFollowUpsController(CreateCommunityFollowUpHandler createCommunityFollowUpHandler,
        CompleteCommunityFollowUpHandler completeCommunityFollowUpHandler,
        ListCommunityFollowUpsHandler listCommunityFollowUpsHandler) {
        this.createCommunityFollowUpHandler = createCommunityFollowUpHandler;
        this.completeCommunityFollowUpHandler = completeCommunityFollowUpHandler;
        this.listCommunityFollowUpsHandler = listCommunityFollowUpsHandler;
    }
    @Override
    public Created createCommunityFollowUp(UUID communityId, CreateCommunityFollowUpBody body,
        OAuth2AuthenticatedPrincipal actor) {
        return new Created(
            createCommunityFollowUpHandler.handle(new CreateCommunityFollowUpCommand(UUID.fromString(actor.getName()),
                body.requestId(), communityId, body.personId(), body.note(), body.dueOn())));
    }
    @Override
    public void completeCommunityFollowUp(UUID followUpId, CompleteCommunityFollowUpBody body,
        OAuth2AuthenticatedPrincipal actor) {
        completeCommunityFollowUpHandler.handle(new CompleteCommunityFollowUpCommand(UUID.fromString(actor.getName()),
            followUpId, body.expectedVersion(), body.reason()));
    }
    @Override
    public List<CommunityReader.FollowUp> listCommunityFollowUps(UUID communityId, boolean completed, int limit,
        int offset) {
        return listCommunityFollowUpsHandler
            .handle(new ListCommunityFollowUpsQuery(communityId, completed, limit, offset));
    }
}

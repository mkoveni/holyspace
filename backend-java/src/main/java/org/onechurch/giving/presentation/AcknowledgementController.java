package org.onechurch.giving.presentation;
import org.onechurch.giving.application.command.issueacknowledgement.IssueAcknowledgementCommand;
import org.onechurch.giving.application.command.issueacknowledgement.IssueAcknowledgementHandler;
import org.onechurch.giving.application.contract.AcknowledgementReader;
import org.onechurch.giving.application.query.getacknowledgement.GetAcknowledgementHandler;
import org.onechurch.giving.application.query.getacknowledgement.GetAcknowledgementQuery;
import org.onechurch.giving.application.query.getgiftacknowledgement.GetGiftAcknowledgementHandler;
import org.onechurch.giving.application.query.getgiftacknowledgement.GetGiftAcknowledgementQuery;
import org.onechurch.giving.presentation.api.AcknowledgementApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class AcknowledgementController implements AcknowledgementApi {
    private final IssueAcknowledgementHandler issueAcknowledgement;
    private final GetGiftAcknowledgementHandler getGiftAcknowledgement;
    private final GetAcknowledgementHandler getAcknowledgement;
    public AcknowledgementController(IssueAcknowledgementHandler issueAcknowledgement,
        GetGiftAcknowledgementHandler getGiftAcknowledgement, GetAcknowledgementHandler getAcknowledgement) {
        this.issueAcknowledgement = issueAcknowledgement;
        this.getGiftAcknowledgement = getGiftAcknowledgement;
        this.getAcknowledgement = getAcknowledgement;
    }
    private UUID actorId(OAuth2AuthenticatedPrincipal actor) {
        return UUID.fromString(actor.getName());
    }
    @Override
    @PreAuthorize("hasAuthority('giving.acknowledge')")
    public Created issueAcknowledgement(UUID giftId, IssueBody body, OAuth2AuthenticatedPrincipal actor) {
        return new Created(
            issueAcknowledgement.handle(new IssueAcknowledgementCommand(actorId(actor), body.requestId(), giftId)));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public AcknowledgementReader.Status getGiftAcknowledgement(UUID giftId) {
        return getGiftAcknowledgement.handle(new GetGiftAcknowledgementQuery(giftId));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public AcknowledgementReader.View getAcknowledgement(UUID acknowledgementId) {
        return getAcknowledgement.handle(new GetAcknowledgementQuery(acknowledgementId));
    }
}

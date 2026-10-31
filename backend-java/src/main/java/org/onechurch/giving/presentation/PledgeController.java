package org.onechurch.giving.presentation;
import org.onechurch.giving.application.command.assignpledgegift.AssignPledgeGiftCommand;
import org.onechurch.giving.application.command.assignpledgegift.AssignPledgeGiftHandler;
import org.onechurch.giving.application.command.cancelpledge.CancelPledgeCommand;
import org.onechurch.giving.application.command.cancelpledge.CancelPledgeHandler;
import org.onechurch.giving.application.command.cancelpledgeassignment.CancelPledgeAssignmentCommand;
import org.onechurch.giving.application.command.cancelpledgeassignment.CancelPledgeAssignmentHandler;
import org.onechurch.giving.application.command.createpledge.CreatePledgeCommand;
import org.onechurch.giving.application.command.createpledge.CreatePledgeHandler;
import org.onechurch.giving.application.contract.PledgeReader;
import org.onechurch.giving.application.query.getpledge.GetPledgeHandler;
import org.onechurch.giving.application.query.getpledge.GetPledgeQuery;
import org.onechurch.giving.application.query.listpledgegiftcandidates.ListPledgeGiftCandidatesHandler;
import org.onechurch.giving.application.query.listpledgegiftcandidates.ListPledgeGiftCandidatesQuery;
import org.onechurch.giving.application.query.listpledges.ListPledgesHandler;
import org.onechurch.giving.application.query.listpledges.ListPledgesQuery;
import org.onechurch.giving.presentation.api.PledgeApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
public class PledgeController implements PledgeApi {
    private final CreatePledgeHandler createPledge;
    private final CancelPledgeHandler cancelPledge;
    private final AssignPledgeGiftHandler assignPledgeGift;
    private final CancelPledgeAssignmentHandler cancelPledgeAssignment;
    private final ListPledgesHandler listPledges;
    private final GetPledgeHandler getPledge;
    private final ListPledgeGiftCandidatesHandler listPledgeGiftCandidates;
    public PledgeController(CreatePledgeHandler createPledge, CancelPledgeHandler cancelPledge,
        AssignPledgeGiftHandler assignPledgeGift, CancelPledgeAssignmentHandler cancelPledgeAssignment,
        ListPledgesHandler listPledges, GetPledgeHandler getPledge,
        ListPledgeGiftCandidatesHandler listPledgeGiftCandidates) {
        this.createPledge = createPledge;
        this.cancelPledge = cancelPledge;
        this.assignPledgeGift = assignPledgeGift;
        this.cancelPledgeAssignment = cancelPledgeAssignment;
        this.listPledges = listPledges;
        this.getPledge = getPledge;
        this.listPledgeGiftCandidates = listPledgeGiftCandidates;
    }
    private UUID actorId(OAuth2AuthenticatedPrincipal actor) {
        return UUID.fromString(actor.getName());
    }
    @Override
    @PreAuthorize("hasAuthority('giving.manage')")
    public Created createPledge(CreatePledgeBody body, OAuth2AuthenticatedPrincipal actor) {
        return new Created(createPledge.handle(new CreatePledgeCommand(actorId(actor), body.requestId(), body.donorId(),
            body.fundId(), body.amountMinor().longValueExact(), body.currency(), body.startsOn(), body.endsOn())));
    }
    @Override
    @PreAuthorize("hasAuthority('giving.manage')")
    public void cancelPledge(UUID pledgeId, CancelBody body, OAuth2AuthenticatedPrincipal actor) {
        cancelPledge.handle(new CancelPledgeCommand(actorId(actor), pledgeId, body.expectedVersion(), body.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('giving.manage')")
    public Created assignPledgeGift(UUID pledgeId, AssignBody body, OAuth2AuthenticatedPrincipal actor) {
        return new Created(assignPledgeGift.handle(new AssignPledgeGiftCommand(actorId(actor), body.requestId(),
            pledgeId, body.giftId(), body.expectedVersion(), body.amountMinor().longValueExact())));
    }
    @Override
    @PreAuthorize("hasAuthority('giving.manage')")
    public void cancelPledgeAssignment(UUID assignmentId, CancelBody body, OAuth2AuthenticatedPrincipal actor) {
        cancelPledgeAssignment.handle(
            new CancelPledgeAssignmentCommand(actorId(actor), assignmentId, body.expectedVersion(), body.reason()));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public PledgeReader.Page listPledges(UUID donorId, String status, int limit, int offset) {
        return listPledges.handle(new ListPledgesQuery(donorId, status, limit, offset));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public PledgeReader.Detail getPledge(UUID pledgeId, int limit, int offset) {
        return getPledge.handle(new GetPledgeQuery(pledgeId, limit, offset));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public List<PledgeReader.Candidate> listPledgeGiftCandidates(UUID pledgeId, int limit, int offset) {
        return listPledgeGiftCandidates.handle(new ListPledgeGiftCandidatesQuery(pledgeId, limit, offset));
    }
}

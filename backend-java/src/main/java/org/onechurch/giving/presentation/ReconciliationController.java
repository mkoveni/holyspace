package org.onechurch.giving.presentation;
import org.onechurch.giving.application.command.createreconciliation.CreateReconciliationCommand;
import org.onechurch.giving.application.command.createreconciliation.CreateReconciliationHandler;
import org.onechurch.giving.application.command.reviewreconciliation.ReviewReconciliationCommand;
import org.onechurch.giving.application.command.reviewreconciliation.ReviewReconciliationHandler;
import org.onechurch.giving.application.contract.ReconciliationReader;
import org.onechurch.giving.application.query.getreconciliation.GetReconciliationHandler;
import org.onechurch.giving.application.query.getreconciliation.GetReconciliationQuery;
import org.onechurch.giving.application.query.listreconciliations.ListReconciliationsHandler;
import org.onechurch.giving.application.query.listreconciliations.ListReconciliationsQuery;
import org.onechurch.giving.presentation.api.ReconciliationApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class ReconciliationController implements ReconciliationApi {
    private final CreateReconciliationHandler createReconciliation;
    private final ReviewReconciliationHandler reviewReconciliation;
    private final ListReconciliationsHandler listReconciliations;
    private final GetReconciliationHandler getReconciliation;
    public ReconciliationController(CreateReconciliationHandler createReconciliation,
        ReviewReconciliationHandler reviewReconciliation, ListReconciliationsHandler listReconciliations,
        GetReconciliationHandler getReconciliation) {
        this.createReconciliation = createReconciliation;
        this.reviewReconciliation = reviewReconciliation;
        this.listReconciliations = listReconciliations;
        this.getReconciliation = getReconciliation;
    }
    private UUID actorId(OAuth2AuthenticatedPrincipal actor) {
        return UUID.fromString(actor.getName());
    }
    @Override
    @PreAuthorize("hasAuthority('giving.reconcile')")
    public Created createReconciliation(ReconcileBody body, OAuth2AuthenticatedPrincipal actor) {
        return new Created(createReconciliation.handle(new CreateReconciliationCommand(actorId(actor), body.requestId(),
            body.reference(), body.expectedMinor().longValueExact(), body.currency(), body.reconciledOn(),
            body.reason(), body.giftIds())));
    }
    @Override
    @PreAuthorize("hasAuthority('giving.reconcile')")
    public Created reviewReconciliation(UUID batchId, ReviewBody body, OAuth2AuthenticatedPrincipal actor) {
        return new Created(reviewReconciliation.handle(new ReviewReconciliationCommand(actorId(actor), body.requestId(),
            batchId, body.expectedVersion(), body.outcome(), body.note())));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public ReconciliationReader.Page listReconciliations(int limit, int offset) {
        return listReconciliations.handle(new ListReconciliationsQuery(limit, offset));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public ReconciliationReader.Detail getReconciliation(UUID batchId) {
        return getReconciliation.handle(new GetReconciliationQuery(batchId));
    }
}

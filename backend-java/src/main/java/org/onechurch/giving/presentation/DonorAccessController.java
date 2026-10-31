package org.onechurch.giving.presentation;
import org.onechurch.giving.application.command.setonlinefund.SetOnlineFundCommand;
import org.onechurch.giving.application.command.setonlinefund.SetOnlineFundHandler;
import org.onechurch.giving.application.command.verifydonorlink.VerifyDonorLinkCommand;
import org.onechurch.giving.application.command.verifydonorlink.VerifyDonorLinkHandler;
import org.onechurch.giving.application.query.listdonoraccess.ListDonorAccessHandler;
import org.onechurch.giving.application.query.listdonoraccess.ListDonorAccessQuery;
import org.onechurch.giving.presentation.api.DonorAccessApi;
import org.springframework.http.CacheControl;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class DonorAccessController implements DonorAccessApi {
    private final ListDonorAccessHandler list;
    private final VerifyDonorLinkHandler verify;
    private final SetOnlineFundHandler funds;
    public DonorAccessController(ListDonorAccessHandler list, VerifyDonorLinkHandler verify,
        SetOnlineFundHandler funds) {
        this.list = list;
        this.verify = verify;
        this.funds = funds;
    }
    @Override
    @PreAuthorize("hasAuthority('identity.manage') and hasAuthority('giving.manage')")
    public ResponseEntity<ListDonorAccessHandler.Result> list(int limit, int offset) {
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .body(list.handle(new ListDonorAccessQuery(limit, offset)));
    }
    @Override
    @PreAuthorize("hasAuthority('identity.manage') and hasAuthority('giving.manage')")
    public void link(UUID accountId, LinkBody b, OAuth2AuthenticatedPrincipal actor) {
        verify.handle(new VerifyDonorLinkCommand(UUID.fromString(actor.getName()), accountId, b.personId(), b.active(),
            b.expectedVersion(), b.evidence()));
    }
    @Override
    @PreAuthorize("hasAuthority('giving.manage')")
    public void fund(UUID fundId, FundBody b, OAuth2AuthenticatedPrincipal actor) {
        funds.handle(
            new SetOnlineFundCommand(UUID.fromString(actor.getName()), fundId, b.enabled(), b.expectedVersion()));
    }
}

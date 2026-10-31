package org.onechurch.identityaccess.presentation;
import org.onechurch.identityaccess.application.command.verifymemberlink.VerifyMemberLinkCommand;
import org.onechurch.identityaccess.application.command.verifymemberlink.VerifyMemberLinkHandler;
import org.onechurch.identityaccess.application.query.listmemberaccess.ListMemberAccessHandler;
import org.onechurch.identityaccess.application.query.listmemberaccess.ListMemberAccessQuery;
import org.onechurch.identityaccess.presentation.api.MemberAccessApi;
import org.springframework.http.CacheControl;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
@PreAuthorize("hasAuthority('identity.manage') and hasAuthority('people.manage')")
public class MemberAccessController implements MemberAccessApi {
    private final ListMemberAccessHandler list;
    private final VerifyMemberLinkHandler verify;
    public MemberAccessController(ListMemberAccessHandler list, VerifyMemberLinkHandler verify) {
        this.list = list;
        this.verify = verify;
    }
    @Override
    public ResponseEntity<ListMemberAccessHandler.Page> list(int limit, int offset) {
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .body(list.handle(new ListMemberAccessQuery(limit, offset)));
    }
    @Override
    public void verify(UUID accountId, LinkBody b, OAuth2AuthenticatedPrincipal actor) {
        verify.handle(new VerifyMemberLinkCommand(UUID.fromString(actor.getName()), accountId, b.personId(), b.active(),
            b.expectedVersion(), b.evidence()));
    }
}

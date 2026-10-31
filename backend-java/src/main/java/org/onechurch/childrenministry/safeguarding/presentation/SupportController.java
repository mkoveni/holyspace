package org.onechurch.childrenministry.safeguarding.presentation;
import org.onechurch.childrenministry.safeguarding.application.command.accesschildsupport.AccessChildSupportCommand;
import org.onechurch.childrenministry.safeguarding.application.command.accesschildsupport.AccessChildSupportHandler;
import org.onechurch.childrenministry.safeguarding.application.command.purgeexpiredsupport.PurgeExpiredSupportCommand;
import org.onechurch.childrenministry.safeguarding.application.command.purgeexpiredsupport.PurgeExpiredSupportHandler;
import org.onechurch.childrenministry.safeguarding.application.command.savechildsupport.SaveChildSupportCommand;
import org.onechurch.childrenministry.safeguarding.application.command.savechildsupport.SaveChildSupportHandler;
import org.onechurch.childrenministry.safeguarding.domain.SupportRecord;
import org.onechurch.childrenministry.safeguarding.presentation.api.SupportApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class SupportController implements SupportApi {
    private final SaveChildSupportHandler saveChildSupport;
    private final AccessChildSupportHandler accessChildSupport;
    private final PurgeExpiredSupportHandler purgeExpiredSupport;
    public SupportController(SaveChildSupportHandler saveChildSupport, AccessChildSupportHandler accessChildSupport,
        PurgeExpiredSupportHandler purgeExpiredSupport) {
        this.saveChildSupport = saveChildSupport;
        this.accessChildSupport = accessChildSupport;
        this.purgeExpiredSupport = purgeExpiredSupport;
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public void saveChildSupport(SaveChildSupportRequest body, OAuth2AuthenticatedPrincipal principal) {
        saveChildSupport.handle(new SaveChildSupportCommand(UUID.fromString(principal.getName()), body.childId(),
            body.expectedVersion(), body.allergies(), body.careInstructions(), body.restrictedNotes(), body.reviewOn(),
            body.retainUntil()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.support')")
    public SupportRecord accessChildSupport(AccessChildSupportRequest body, OAuth2AuthenticatedPrincipal principal) {
        return accessChildSupport.handle(
            new AccessChildSupportCommand(UUID.fromString(principal.getName()), body.childId(), body.sessionId()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.support')")
    public int purgeExpiredSupport(PurgeExpiredSupportRequest body, OAuth2AuthenticatedPrincipal principal) {
        return purgeExpiredSupport
            .handle(new PurgeExpiredSupportCommand(UUID.fromString(principal.getName()), body.limit()));
    }
}

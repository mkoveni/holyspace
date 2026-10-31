package org.onechurch.childrenministry.safeguarding.presentation;
import org.onechurch.childrenministry.safeguarding.application.SafeguardingView;
import org.onechurch.childrenministry.safeguarding.application.command.authorizepickup.AuthorizePickupCommand;
import org.onechurch.childrenministry.safeguarding.application.command.authorizepickup.AuthorizePickupHandler;
import org.onechurch.childrenministry.safeguarding.application.command.recordconsent.RecordConsentCommand;
import org.onechurch.childrenministry.safeguarding.application.command.recordconsent.RecordConsentHandler;
import org.onechurch.childrenministry.safeguarding.application.command.revokeconsent.RevokeConsentCommand;
import org.onechurch.childrenministry.safeguarding.application.command.revokeconsent.RevokeConsentHandler;
import org.onechurch.childrenministry.safeguarding.application.command.revokeguardian.RevokeGuardianCommand;
import org.onechurch.childrenministry.safeguarding.application.command.revokeguardian.RevokeGuardianHandler;
import org.onechurch.childrenministry.safeguarding.application.command.revokepickupauthorization.RevokePickupAuthorizationCommand;
import org.onechurch.childrenministry.safeguarding.application.command.revokepickupauthorization.RevokePickupAuthorizationHandler;
import org.onechurch.childrenministry.safeguarding.application.command.verifyguardian.VerifyGuardianCommand;
import org.onechurch.childrenministry.safeguarding.application.command.verifyguardian.VerifyGuardianHandler;
import org.onechurch.childrenministry.safeguarding.application.query.getsafeguarding.GetSafeguardingHandler;
import org.onechurch.childrenministry.safeguarding.application.query.getsafeguarding.GetSafeguardingQuery;
import org.onechurch.childrenministry.safeguarding.presentation.api.SafeguardingApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class SafeguardingController implements SafeguardingApi {
    private final VerifyGuardianHandler verifyGuardian;
    private final RecordConsentHandler recordConsent;
    private final RevokeGuardianHandler revokeGuardian;
    private final RevokeConsentHandler revokeConsent;
    private final RevokePickupAuthorizationHandler revokePickupAuthorization;
    private final AuthorizePickupHandler authorizePickup;
    private final GetSafeguardingHandler getSafeguarding;
    public SafeguardingController(VerifyGuardianHandler verifyGuardian, RecordConsentHandler recordConsent,
        RevokeGuardianHandler revokeGuardian, RevokeConsentHandler revokeConsent,
        RevokePickupAuthorizationHandler revokePickupAuthorization, AuthorizePickupHandler authorizePickup,
        GetSafeguardingHandler getSafeguarding) {
        this.verifyGuardian = verifyGuardian;
        this.recordConsent = recordConsent;
        this.revokeGuardian = revokeGuardian;
        this.revokeConsent = revokeConsent;
        this.revokePickupAuthorization = revokePickupAuthorization;
        this.authorizePickup = authorizePickup;
        this.getSafeguarding = getSafeguarding;
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public Created verifyGuardian(VerifyGuardianRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(verifyGuardian.handle(new VerifyGuardianCommand(UUID.fromString(principal.getName()),
            body.childId(), body.guardianId(), body.validFrom(), body.validUntil(), body.evidenceReference())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public Created recordConsent(RecordConsentRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(recordConsent.handle(new RecordConsentCommand(UUID.fromString(principal.getName()),
            body.childId(), body.programmeId(), body.policyId(), body.authorityId(), body.validFrom(),
            body.validUntil(), body.acceptedAt(), body.evidenceReference())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public void revokeGuardian(RevokeGuardianRequest body, OAuth2AuthenticatedPrincipal principal) {
        revokeGuardian.handle(new RevokeGuardianCommand(UUID.fromString(principal.getName()), body.id(),
            body.expectedVersion(), body.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public void revokeConsent(RevokeConsentRequest body, OAuth2AuthenticatedPrincipal principal) {
        revokeConsent.handle(new RevokeConsentCommand(UUID.fromString(principal.getName()), body.id(),
            body.expectedVersion(), body.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public void revokePickupAuthorization(RevokePickupAuthorizationRequest body,
        OAuth2AuthenticatedPrincipal principal) {
        revokePickupAuthorization.handle(new RevokePickupAuthorizationCommand(UUID.fromString(principal.getName()),
            body.id(), body.expectedVersion(), body.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public Created authorizePickup(AuthorizePickupRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(authorizePickup.handle(new AuthorizePickupCommand(UUID.fromString(principal.getName()),
            body.programmeId(), body.childId(), body.collectorId(), body.authorityId(), body.validFrom(),
            body.validUntil(), body.evidenceReference())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public SafeguardingView getSafeguarding(UUID childId) {
        return getSafeguarding.handle(new GetSafeguardingQuery(childId));
    }
}

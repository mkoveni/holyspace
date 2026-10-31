package org.onechurch.childrenministry.safeguarding.application.query.getconsentpolicy;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.safeguarding.domain.ConsentPolicy;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class GetConsentPolicyHandler {
    private final PermissionChecker authorization;
    private final ParticipationReader participation;
    public GetConsentPolicyHandler(PermissionChecker authorization, ParticipationReader participation) {
        this.authorization = authorization;
        this.participation = participation;
    }
    public ConsentPolicy handle(GetConsentPolicyQuery query) {
        authorization.requirePermission("children.read");
        return participation.consentPolicy(query.policyId())
            .orElseThrow(() -> ChildrenException.missing("Consent policy"));
    }
}

package org.onechurch.childrenministry.messaging.application.query.getguardiancommunication;
import org.onechurch.childrenministry.messaging.application.GuardianCommunicationPolicy;
import org.onechurch.childrenministry.messaging.application.contract.GuardianCommunicationReader;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.time.Clock;
import java.time.Instant;
import java.util.Objects;
public final class GetGuardianCommunicationHandler {
    private final PermissionChecker auth;
    private final GuardianCommunicationReader reader;
    private final GuardianCommunicationPolicy policy;
    private final Clock clock;
    public GetGuardianCommunicationHandler(PermissionChecker auth, GuardianCommunicationReader reader,
        GuardianCommunicationPolicy policy, Clock clock) {
        this.auth = auth;
        this.reader = reader;
        this.policy = policy;
        this.clock = clock;
    }
    public GuardianCommunication handle(GetGuardianCommunicationQuery q) {
        auth.requirePermission("children.safeguarding");
        GuardianCommunication c =
            reader.find(q.communicationId()).orElseThrow(() -> ChildrenException.missing("Communication"));
        if (c.status().equals("DRAFT")) {
            Instant now = clock.instant();
            policy.guardian(c.childId(), c.authorityId(), now);
            Programme p = policy.programme(c.programmeId(), c.childId(), c.type(), c.sessionId(), now);
            if (c.type().equals("CONSENT_RENEWAL") && !Objects.equals(c.policyId(), p.currentPolicyId())) {
                throw ChildrenException
                    .conflict("The consent policy changed. Cancel this draft and prepare a current request.");
            }
        }
        return c;
    }
}

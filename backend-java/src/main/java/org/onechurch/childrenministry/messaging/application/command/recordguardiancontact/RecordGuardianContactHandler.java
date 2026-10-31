package org.onechurch.childrenministry.messaging.application.command.recordguardiancontact;
import org.onechurch.childrenministry.messaging.application.GuardianCommunicationPolicy;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;
import org.onechurch.childrenministry.messaging.domain.repository.GuardianCommunicationRepository;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Locale;
import java.util.Objects;
public final class RecordGuardianContactHandler {
    private final PermissionChecker auth;
    private final GuardianCommunicationPolicy policy;
    private final GuardianCommunicationRepository repository;
    private final SafeguardingRepository safeguarding;
    private final UnitOfWork tx;
    private final ChildrenAudit audit;
    private final Clock clock;
    private final DeliveryReader deliveries;
    public RecordGuardianContactHandler(PermissionChecker auth, GuardianCommunicationPolicy policy,
        GuardianCommunicationRepository repository, SafeguardingRepository safeguarding, UnitOfWork tx,
        ChildrenAudit audit, Clock clock, DeliveryReader deliveries) {
        this.auth = auth;
        this.policy = policy;
        this.repository = repository;
        this.safeguarding = safeguarding;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
        this.deliveries = deliveries;
    }
    public void handle(RecordGuardianContactCommand c) {
        auth.requirePermission(c.actorId(), "children.safeguarding");
        tx.execute(() -> {
            GuardianCommunication current =
                repository.lock(c.communicationId()).orElseThrow(() -> ChildrenException.missing("Communication"));
            if (deliveries.forCommunication(current.id()).filter(d -> !d.status().equals("CANCELLED")).isPresent()) {
                throw ChildrenException.conflict(
                    "Review the existing email delivery before recording manual contact or cancelling this communication.");
            }
            safeguarding.guardianAuthorityForUpdate(current.authorityId())
                .orElseThrow(() -> ChildrenException.missing("Guardian authority"));
            Instant now = clock.instant();
            if ("CONTACTED".equals(c.status())) {
                policy.guardian(current.childId(), current.authorityId(), now);
                Programme programme = policy.programme(current.programmeId(), current.childId(), current.type(),
                    current.sessionId(), now);
                if (current.type().equals("CONSENT_RENEWAL")
                    && !Objects.equals(current.policyId(), programme.currentPolicyId())) {
                    throw ChildrenException
                        .conflict("The consent policy changed. Cancel this draft and prepare a current request.");
                }
            }
            GuardianCommunication changed =
                current.finish(c.expectedVersion(), c.status(), c.reference(), c.actorId(), now);
            repository.update(changed);
            audit.record(c.actorId(), "guardian.communication." + changed.status().toLowerCase(Locale.ROOT),
                changed.id(), "", now);
            return null;
        });
    }
}

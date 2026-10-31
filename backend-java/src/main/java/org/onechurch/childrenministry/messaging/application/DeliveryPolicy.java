package org.onechurch.childrenministry.messaging.application;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.messaging.application.contract.GuardianCommunicationReader;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;
import org.onechurch.childrenministry.messaging.domain.VerifiedGuardianEmail;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.shared.application.RequestFingerprint;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.people.application.contract.PersonEmailDirectory;

import java.time.Instant;
import java.util.Objects;
import java.util.UUID;
public final class DeliveryPolicy {
    private final GuardianCommunicationPolicy policy;
    private final GuardianCommunicationReader communications;
    private final DeliveryReader reader;
    private final PersonEmailDirectory emails;
    private final EmailTemplates templates;
    public DeliveryPolicy(GuardianCommunicationPolicy policy, GuardianCommunicationReader communications,
        DeliveryReader reader, PersonEmailDirectory emails, EmailTemplates templates) {
        this.policy = policy;
        this.communications = communications;
        this.reader = reader;
        this.emails = emails;
        this.templates = templates;
    }
    public GuardianCommunication current(UUID id, Instant now) {
        GuardianCommunication c = communications.find(id).orElseThrow(() -> ChildrenException.missing("Communication"));
        if (!c.status().equals("DRAFT")) {
            throw ChildrenException.conflict("Only draft communications can be emailed.");
        }
        policy.guardian(c.childId(), c.authorityId(), now);
        Programme p = policy.programme(c.programmeId(), c.childId(), c.type(), c.sessionId(), now);
        if (c.type().equals("CONSENT_RENEWAL") && !Objects.equals(c.policyId(), p.currentPolicyId())) {
            throw ChildrenException.conflict("The programme consent policy changed. Prepare a current request.");
        }
        return c;
    }
    public VerifiedGuardianEmail verified(UUID id, GuardianCommunication c, Instant now) {
        return reader.verification(id)
            .filter(v -> v.guardianId().equals(c.guardianId()) && v.valid(now))
            .filter(v -> emails.active(c.guardianId())
                .stream()
                .anyMatch(e -> e.id().equals(v.contactId()) && e.value().equals(v.email())))
            .orElseThrow(() -> ChildrenException
                .conflict("A current verification of the guardian's unchanged active email contact is required."));
    }
    private String sourceHash(GuardianCommunication c, VerifiedGuardianEmail email) {
        return RequestFingerprint.of(c.id(), c.version(), c.message(), email.id(), email.email(), email.contactId());
    }
    public EmailTemplates.Rendered render(GuardianCommunication c) {
        return templates.render(
            c.type().equals("CONSENT_RENEWAL") ? "children.consent-renewal" : "children.session-reminder",
            policy.variables(c));
    }
    public String hash(GuardianCommunication c, VerifiedGuardianEmail email, EmailTemplates.Rendered rendered) {
        return RequestFingerprint.of(sourceHash(c, email), rendered.subject(), rendered.html(), rendered.key(),
            rendered.version());
    }
    public void recheck(DeliverySnapshot delivery, Instant now) {
        GuardianCommunication c = current(delivery.communicationId(), now);
        VerifiedGuardianEmail email = verified(delivery.verificationId(), c, now);
        if (!delivery.reviewHash()
            .equals(delivery.contentType().equals("text/plain") ? sourceHash(c, email)
                : RequestFingerprint.of(sourceHash(c, email), delivery.subject(), delivery.body(),
                    delivery.templateKey(), delivery.templateVersion()))) {
            throw ChildrenException.conflict("The reviewed message or recipient changed.");
        }
    }
    public void schedule(GuardianCommunication c, Instant at) {
        policy.guardian(c.childId(), c.authorityId(), at);
        policy.programme(c.programmeId(), c.childId(), c.type(), c.sessionId(), at);
    }

}

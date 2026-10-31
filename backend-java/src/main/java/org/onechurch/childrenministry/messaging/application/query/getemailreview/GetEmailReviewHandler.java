package org.onechurch.childrenministry.messaging.application.query.getemailreview;
import org.onechurch.childrenministry.messaging.application.DeliveryPolicy;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;
import org.onechurch.childrenministry.messaging.domain.VerifiedGuardianEmail;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.communications.application.contract.EmailTransport;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonEmailDirectory;

import java.time.Clock;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
public final class GetEmailReviewHandler {
    public record Recipient(UUID contactId, String email, UUID verificationId, Instant validUntil, String reviewHash) {}
    public record View(UUID communicationId, long version, String message, List<Recipient> recipients,
        EmailTransport.Configuration transport, String subject, String html, String templateKey, long templateVersion) {
        @Override
        public String toString() {
            return "EmailReview[restricted]";
        }
    }
    private final PermissionChecker auth;
    private final DeliveryPolicy policy;
    private final DeliveryReader reader;
    private final PersonEmailDirectory emails;
    private final EmailTransport transport;
    private final Clock clock;
    public GetEmailReviewHandler(PermissionChecker auth, DeliveryPolicy policy, DeliveryReader reader,
        PersonEmailDirectory emails, EmailTransport transport, Clock clock) {
        this.auth = auth;
        this.policy = policy;
        this.reader = reader;
        this.emails = emails;
        this.transport = transport;
        this.clock = clock;
    }
    public View handle(GetEmailReviewQuery q) {
        auth.requirePermission("children.safeguarding");
        GuardianCommunication c = policy.current(q.communicationId(), clock.instant());
        EmailTemplates.Rendered rendered = policy.render(c);
        List<VerifiedGuardianEmail> verified = reader.emails(c.guardianId());
        return new View(c.id(), c.version(), c.message(), emails.active(c.guardianId()).stream().map(email -> {
            VerifiedGuardianEmail v = verified.stream()
                .filter(value -> value.contactId().equals(email.id()) && value.email().equals(email.value())
                    && value.valid(clock.instant()))
                .findFirst()
                .orElse(null);
            return new Recipient(email.id(), email.value(), v == null ? null : v.id(),
                v == null ? null : v.validUntil(), v == null ? null : policy.hash(c, v, rendered));
        }).toList(), transport.configuration(), rendered.subject(), rendered.html(), rendered.key(),
            rendered.version());
    }
}

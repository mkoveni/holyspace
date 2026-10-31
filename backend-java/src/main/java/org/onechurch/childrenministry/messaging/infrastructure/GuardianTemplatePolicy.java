package org.onechurch.childrenministry.messaging.infrastructure;
import org.onechurch.communications.application.contract.TemplatePublicationPolicy;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.Optional;
import java.util.Set;
@Component
public final class GuardianTemplatePolicy implements TemplatePublicationPolicy {
    public Optional<String> violation(String key, String subject, String html, List<String> variables) {
        if (!key.startsWith("children.")) {
            return Optional.empty();
        }
        boolean known = Set.of("message", "guardianName", "childName", "programmeName", "sessionDate", "policyName")
            .containsAll(variables);
        boolean retainsMessage = html.matches("(?s).*\\{\\{\\s*message\\s*\\}\\}.*");
        return known && retainsMessage ? Optional.empty() : Optional.of(
            "Children ministry templates must retain {{message}} in the body and use only message, guardianName, childName, programmeName, sessionDate or policyName.");
    }
}

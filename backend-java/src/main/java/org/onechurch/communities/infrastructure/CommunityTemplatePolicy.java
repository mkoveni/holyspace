package org.onechurch.communities.infrastructure;
import org.onechurch.communications.application.contract.TemplatePublicationPolicy;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.Optional;
@Component
public class CommunityTemplatePolicy implements TemplatePublicationPolicy {
    public Optional<String> violation(String key, String subject, String html, List<String> variables) {
        if (!key.equals("communities.notice")) {
            return Optional.empty();
        }
        List<String> required = List.of("name", "community", "message");
        if (!variables.containsAll(required) || variables.stream().anyMatch(v -> !required.contains(v))
            || !html.contains("{{message}}") || !(subject + html).contains("{{community}}")) {
            return Optional.of(
                "Member notices require name, community and message variables, with message in the body and community in the rendered notice.");
        }
        return Optional.empty();
    }
}

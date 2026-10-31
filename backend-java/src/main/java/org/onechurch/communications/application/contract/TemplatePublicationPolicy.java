package org.onechurch.communications.application.contract;
import java.util.List;
import java.util.Optional;
/** Originating contexts may require particular variables or mandatory wording. */
public interface TemplatePublicationPolicy {
    Optional<String> violation(String key, String subject, String html, List<String> variables);
}
